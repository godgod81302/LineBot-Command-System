<?php
namespace App\Line;

use Illuminate\Support\Facades\Log;
use App\Model\Server;
use App\Model\Country;
use App\Model\LineUser;
use App\Model\LineGroup;
use App\Model\PartnerGroupPair;
use App\Line\ApiHelper;
use App\Model\GroupUserPair;
use App\Command\CommandManager;
use App\Command\CustomCanUp;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class EventHandler{
	
	private $helper;
	
	public function __construct( $token ){
		$this->helper = ApiHelper::helper( $token );
	}

	public function handle( $event ){

		$type = $event->type;
		if( !method_exists($this, $type) )
			return;

		$allow_func = [
			'message','join','leave','unsend','follow','unfollow',
			'memberJoined','memberLeft','postback','videoPlayComplete',
			'beacon','things',
		];
		if( array_search($type, $allow_func)!==false )
			$this->$type( $event );
	}

	private function message( $event ){

		if ( $event->message->type == 'image' ){
			// Storage::disk('Server_images')->put($event->message->id.'.jpg', $this->helper->getContent($event->message->id));
			$msg = $event->message->id;
		}
		else if( $event->message->type == 'text' ){
			$msg = $event->message->text;
		}
		else{
			exit;
		}
		if ( isset($event->message->mention) ){
			foreach ( $event->message->mention->mentionees as $mention_user ){
				if (isset($mention_user->userId)){
					$this->recordUser(	$mention_user->userId , $event->source->groupId );
				}				
			}
		}

		$group = $this->recordGroup( $event->source->groupId );
		if ( isset($event->source->userId) ){
			$user = $this->recordUser(	$event->source->userId , $group->id );
			$this->recordGroupMember( $event->source->groupId, $event->source->userId);
		}

		if ( $msg == '&關閉' ){
 
			Redis::del(md5($user->id));
			Redis::del(md5($user->id.$group->id));
			$message = '您當前的個人對話已關閉，若要使用請重新下指令';
			$reply_msg = $message;		
		
			$messages = [
				['type'=>'text', 'text'=>$reply_msg]
			];
			print_r($reply_msg);
			$result = $this->helper->reply( $event->replyToken, $messages);
			exit;
		}
		//目前用於設業務，不限定群組的session
		$unsign_group_session = Redis::hgetall(md5($user->id));
		// print_r($unsign_group_session);
		if ( $unsign_group_session ){
			$data = (object)[
				'group' => $group ?? null,
				'user' => $user,
				'command' => $msg,
				'type' => $event->message->type,
			];
			if ( isset($event->message->mention) ){
				$data->mention = $event->message->mention;
			}
			if ( isset($event->is_final_event) ){
				$data->is_final_event = true;
			}
			$class = $unsign_group_session['classname']::getInstance();
			$message =	$class->runSessionFunction($data);

			$reply_msg = $message;
			$messages = [];
			if ( is_array($message) ){
				foreach ( $message as $value ){
					$messages[] = ['type'=>'text', 'text'=>$value];
				}
			}
			else if( is_object($message) ){
				if ($message->type=='image'){
					$messages = [
						['type'=>$message->type, 'originalContentUrl'=>$message->originalContentUrl,'previewImageUrl'=>$message->previewImageUrl],
					];
				}
			}
			// else if (is_bool($message) && !$message ){
			// 	exit;
			// }
			else{
				$messages = [
					['type'=>'text', 'text'=>$reply_msg],
				];
			}
			print_r($messages);
			$result = $this->helper->reply( $event->replyToken, $messages);

			return;
		}


		$session_lifetime = 300;
	// 如果是服務員,將session存活時間延長至30分鐘
		$check_if_server_quest = Server::where('line_user_id',$user->id)->first();
		if ( $check_if_server_quest ){
			$session_lifetime = 1800;
		}

	// 特定使用者在特定群組有session
		$group_session	= Redis::hgetall(md5($user->id.$group->id));
		if ( $group_session ){
			//session時間未結束,接續處理session
			if( (time()-$group_session['timestamp']) < $session_lifetime ){
				Redis::hmset(md5($user->id.$group->id),'timestamp',strtotime('now'));
				$data = (object)[
					'group' => $group ?? null,
					'user' => $user,
					'command' => $msg,
					'type' => $event->message->type,
				];
				if ( isset($event->message->mention) ){
					$data->mention = $event->message->mention;
				}
				if ( isset($event->is_final_event) ){
					$data->is_final_event = true;
				}
				$class = $group_session['classname']::getInstance();
				$message =	$class->runSessionFunction($data);
		
				$reply_msg = $message;
				$messages = [];
				
				if ( is_array($message) ){
					foreach ( $message as $value ){
						if ( is_object($value) ){
							$messages[] = ['type'=>$value->type, 'originalContentUrl'=>$value->originalContentUrl,'previewImageUrl'=>$value->previewImageUrl];
						}
						else{
							$value = trim($value,"\n");
							$messages[] = ['type'=>'text', 'text'=>$value];
						}
					}
	
				}
				else if( is_object($message) ){
					if ( isset($message->result) ){
						if (!$message->result){
							$messages = [
								['type'=>'text', 'text'=>$message->message],
							];
						}
					}
					else if ($message->type=='image'){
						$messages = [
							['type'=>$message->type, 'originalContentUrl'=>$message->originalContentUrl,'previewImageUrl'=>$message->previewImageUrl],
						];
					}
				}
				else{
					if (!empty($reply_msg)){
						$messages = [
							['type'=>'text', 'text'=>$reply_msg],
						];
					}
				}
				print_r($messages);
				$result = $this->helper->reply( $event->replyToken, $messages);
			}
			// session時間超過,移除session
			else{
				Redis::del(md5($user->id.$group->id));
				$message = '抱歉，您先前與機器人的對話已逾時，請重新下指令';
				$reply_msg = $message;
				$messages = [
					['type'=>'text', 'text'=>$reply_msg]
				];
				print_r($reply_msg);
				$result = $this->helper->reply( $event->replyToken, $messages);
			}
			return;
		}
		$pre_command = '';
		$cmd = '';

		// 依照訊息開頭是否有特定指令符號,儲存特定指令符號
		if( strpos($msg, CommandManager::USUAL_PRE_COMMAND)===0 )
			$pre_command = CommandManager::USUAL_PRE_COMMAND;
		elseif( strpos($msg, CommandManager::CTRL_PRE_COMMAND)===0 )
			$pre_command = CommandManager::CTRL_PRE_COMMAND;
		
		$check_schedule = $this->isSchedule($msg,$group->id);

		if ( $check_schedule->result ){
			// 訊息複合班表格式,將訊息加上指令文字,以利後續程式碼處理指令
			$msg = '#班表'.$msg;
			$pre_command = '#';
		}
		else{
			if (	strtolower(substr($msg,0,4))=='#out'  ){
				if ((strlen($msg)-4)>0){
					$msg = '#out'.substr($msg,4,strlen($msg)-4);
				}
				else{
					$msg = '#out';
				}
			}
			if (	strtolower(substr($msg,0,3))=='#in'  ){
				if ((strlen($msg)-3)>0){
					$msg = '#in'.substr($msg,3,strlen($msg)-3);
				}
				else{
					$msg = '#in';
				}
			}
			if ( mb_substr($msg,-2)=='請上'){
				$msg = '#請上'.mb_substr($msg, strlen($pre_command),-2);
			}
			if ( mb_substr($msg,-2)=='客到'){
				$msg = '#客到'.mb_substr($msg, strlen($pre_command),-2);
			}
		}

		if ( !empty($check_schedule->message) ) {
			$messages = [
				['type'=>'text', 'text'=>$check_schedule->message]
			];
			$result = $this->helper->reply( $event->replyToken, $messages);
		}

		if( $pre_command ){			
			$result = CommandManager::init();			
			$command_msg = substr($msg, strlen($pre_command)); // 過濾指令符號			
			$command = CommandManager::getCommand( $msg );

			$reply_msg = "查無相關指令";
			if( $command ){
				$data = (object)[
					'group' => $group ?? null,
					'user' => $user,
					'command' => $command_msg,
				];
				if ( isset($event->message->mention) ){
					$data->mention = $event->message->mention;
				}
				if ( isset($event->is_final_event) ){
					$data->is_final_event = true;
				}
				$message = $command->execute( $data );
				if ($message==false){
					exit;
				}
				$reply_msg = $message;
			}
			//這裡要判斷，如果傳回來的是陣列的話，就直接給多個訊息，如果是物件，就看是啥物件做不同處置

			$messages = [];
			if ( is_array($message) ){
				foreach ( $message as $value ){
					if ( is_object($value) ){
						$messages[] = ['type'=>$value->type, 'originalContentUrl'=>$value->originalContentUrl,'previewImageUrl'=>$value->previewImageUrl];
					}
					else{
						$value = trim($value,"\n");
						$messages[] = ['type'=>'text', 'text'=>$value];
					}
				}

			}
			else if( is_object($message) ){
				if ( isset($message->result) ){
					if (!$message->result){
						$messages = [
							['type'=>'text', 'text'=>$message->message],
						];
					}
				}
				else if ($message->type=='image'){
					$messages = [
						['type'=>$message->type, 'originalContentUrl'=>$message->originalContentUrl,'previewImageUrl'=>$message->previewImageUrl],
					];
				}
			}
			else{
				$reply_msg = trim($reply_msg,"\n");
				$messages = [
					['type'=>'text', 'text'=>$reply_msg],
				];
			}
			$result = $this->helper->reply( $event->replyToken, $messages);
		}
	}
	
	private function join( $event ){
		if( $event->source->type!='group')
			return;
			
		$group = $this->recordGroup( $event->source->groupId );
	}

	private function leave( $event ){

	}

	private function unsend( $event ){

	}

	private function follow( $event ){
		$this->recordUser( $event->source->userId );
	}

	private function unfollow( $event ){

	}

	private function memberJoined( $event ){
		// 只記錄群組成員
		if( $event->source->type!='group' )
			return;
		$group = $this->recordGroup( $event->source->groupId );
	
		$members = $event->joined->members;
		foreach( $members as $member ){
			if( $member->type!='user')
				continue;
			$user_id = $member->userId;
			$this->recordUser( $user_id , $group->id );
			$this->recordGroupMember( $group->id, $user_id );
		}
	}

	private function memberLeft( $event ){

		if( $event->source->type!='group' )
			return;
		$group = $this->recordGroup( $event->source->groupId );
		$members = $event->left->members;

		foreach( $members as $member ){
			if( $member->type!='user')
				continue;
			$user_id = $member->userId;
			$this->removeGroupMember( $group->id, $user_id );
		}
	}

	private function postback( $event ){

	}

	private function videoPlayComplete( $event ){

	}

	private function beacon( $event ){

	}

	private function things( $event ){

	}

	private function recordGroup( $group_id ){
		if( !$group_id )
			return null;

		$group = LineGroup::find( $group_id );
		if( !$group ){
			$group = new LineGroup();
			$group->id = $group_id;
			$group->join_time = date('Y-m-d H:i:s');
		}
		$group_data = $this->helper->getGroupSummary( $group_id );
		$group->name = $group_data->groupName;
		$group->save();
		$group->refresh();
		return $group;
	}

	private function recordUser( $user_id , $group_id=NULL ){		
		// 紀錄用戶資訊
		$user = LineUser::find( $user_id );
		if( !$user ){
			$user = new LineUser();
			$user->id = $user_id;
		}
		$user_data = $this->helper->getUserProfile( $user_id );
		$user->status = 'follow';
		if( !isset($user_data->displayName) ){
			$user->status = 'unfollow';
			$user_data = $this->helper->getGroupMemberProfile( $group_id, $user_id );
		}
		$user->latest_name = $user_data->displayName;
		if (isset($user_data->pictureUrl)){
			$user->latest_img_url = $user_data->pictureUrl;
		}
		$user->save();
		$user->refresh();
		return $user;
	}

	private function recordGroupMember( $group_id, $user_id ){
		$pair = GroupUserPair::where('line_group_id', $group_id)
			->where('line_user_id', $user_id)
			->first();
		if( !$pair ){
			$pair = new GroupUserPair();
			$pair->line_group_id = $group_id;
			$pair->line_user_id = $user_id;
			$pair->save();
			$pair->refresh();
		}
		return $pair;
	}

	private function removeGroupMember( $group_id, $user_id ){		
		$pair = GroupUserPair::where('line_group_id', $group_id)
			->where('line_user_id', $user_id)
			->first();
		if( $pair ){
			$pair->delete();
		}
		return $pair;
	}
	
	//確認傳進來的訊息是班表
	private function isSchedule( &$msg,$group_id ){
		//這裡一行一行拆解出來，判斷是否符合班表規則，因為這裡是event handler，其實在這裡做驗證不是很好，所以這邊只是初步測試，看班表時間是否合法，是不是有待至少一航班表資訊
		//剩下的細部驗證等後面再驗(一方面也是這邊不好給出錯誤訊息，又不好管理)
		$result = (object) [];
		$result->result = false;
		$msg = trim($msg,"\n");
		$msg_array = explode("\n",$msg);

		$line_group = LineGroup::where('id',$group_id)->first();
		$partner_group_pair = PartnerGroupPair::where('line_group_id',$group_id)->where('group_type','Server')->first();

		//如果是班表資訊一定有超過一行;第一行要是日期mm/dd
		if ( preg_match('/^[0-9]{2}\/[0-9]{2}/',$msg_array[0],$date) ){
			$schedule_date = $date[0];
			list($month, $day) = explode('/',$schedule_date);

			// 當日的時間分界,判斷目前時間點屬於哪一天的班
			$work_day_timestamp = time();
			if( date('H')<config('app.system.day_split_hour') ){
				$work_day_timestamp = strtotime('-1 day');
			}
			$year = date('Y',$work_day_timestamp);

			$is_date_legal = checkdate($month,$day,$year);
			//班表給的日期只能是昨天今天明天
			if ( date('m/d',$work_day_timestamp-86400) != $schedule_date &&
					date('m/d',$work_day_timestamp) != $schedule_date && 
					date('m/d',$work_day_timestamp+86400) != $schedule_date ){
				$is_date_legal = false;
				$result->message = '班表給的日期只能是昨天今天或明天';
				print_r($result->message);exit;
			}
			if ( $is_date_legal ){
				$msg = preg_replace('/^[0-9]*\/[0-9]*/',$date[0],$msg);
				$result->result = true;

				if ( $line_group->enable == 'N' || !$partner_group_pair ){
					print_r('非服務群，或者群組沒啟用，所以不執行反解析');
					exit;
				}
			}
		}
		else{
			// 班表日期不合規定,不回傳訊息,有可能使用者輸入有別的用途
			$result->message = null;
		}

		return $result;
	}

}