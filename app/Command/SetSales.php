<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\GroupAdmin;
use App\Model\LineUser;
use App\Model\Sales;
use App\Model\PartnerSalesAuth;
use Illuminate\Support\Facades\Redis;

class SetSales extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new SetSales();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '設定業務',
			'cmd' => '設業務',
			'description' => '設定業務',
      'access' => ['group_admin'],
      'reply_questions' => ['您可以到任何一個有機器人的群組tag您希望設為業務的帳號，但須注意該帳號不得已經是業務'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;

		$message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."(空格){業務群組代碼(三碼，限英數)}(空格){廠商ID},例如:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']." Sa7 2";
    $result = $this->randomkeys();

    $commands = explode(' ',$command);

    if ( !isset($commands[2])){
      if (!isset($commands[1])){
        $message = '您未輸入業務代碼，未被使用過的代碼:'."\n";
        foreach ($result as $value){
          $message .= $value."\n";
        }
        return $message;
      }
      if (is_numeric($commands[1]) && ($commands[1]<100)){
        $message = '您未輸入業務代碼，未被使用過的代碼:'."\n";
        foreach ($result as $value){
          $message .= $value."\n";
        }
        return $message;
      }
      else{
        $group_admin_datas = $user->group_admins;
        if ( count($group_admin_datas) >1 ){
          return '您擁有多個廠商之群組管理員身分，故無法省略廠商id資訊';
        }
        $partner_id = $group_admin_datas->first()->partner_id;
        $sales_group_code = $commands[1];
      }
    }
    else{
      $partner_id = $commands[2];
      $sales_group_code = $commands[1];
    }

    $partner = Partner::find($partner_id);

		if( !$partner ){
      return "查無編號#{$partner_id}的廠商";
    }
      
       
		if( !preg_match('/^[A-Za-z0-9]{3}+$/',$sales_group_code) ){
			return "業務群組代碼格式不正確";
    }

    // if( substr($sales_group_code,-1) == 'e' )
    //   return "業務群組代碼結尾不可為e，因為e為系統保留字";
    $group_admin_datas = $user->group_admins;
    $has_partner_identify = false;
    foreach ( $group_admin_datas as $group_admin_data ){
      if ( $group_admin_data->partner_id == $partner_id ){
        $has_partner_identify = true;
      }
    }
    if ( substr($sales_group_code,0,1)=='t'){
      return 'te開頭的業務群組代碼為系統所保留，請換一個';
    }
    if ( !$has_partner_identify ){
      return '您並非該廠商之群組管理員';
    }
    $msg_list = [];
    
		Redis::del(md5($user->id));
		$session_exist  = Redis::hmget(md5($user->id),'timestamp');

		if ( !$session_exist[0] ){
			 Redis::hmset(md5($user->id),'timestamp',strtotime('now'),'classname',__CLASS__,'msg_list',json_encode($msg_list),'partner_id',$partner_id,'sales_group_code',$sales_group_code);
		}
		
		$message = $this->command_data['reply_questions'][0]."\n".'當前廠商代號:'.$sales_group_code;
		$message = trim($message,"\n");


		return $message;
  }
  // private function randomkeys()   
  // {   
  //   $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';  
  //   $key = '';
  //   $is_not_use_group_code = false;
  //   while(!$is_not_use_group_code){
  //     for($i=0;$i<3;$i++)   
  //     {   
  //         $key .= $pattern{mt_rand(0,35)};   
  //     }  
  //     $partner_sales_auth = PartnerSalesAuth::where('sales_group_code','like','%'.$key.'%')->first();
  //     if (!$partner_sales_auth){
  //       $is_not_use_group_code = true;
  //     }
  //   }

  //   return $key;
  // }   
  private function randomkeys()   
  {   
 
    $choese_array = [];
    $max_number = '';
    for ($i=999;$i>0;$i--){
      $count ='';
      $count = str_pad($i,3,"0",STR_PAD_LEFT);
      $max_partner_sales_auth = PartnerSalesAuth::where('sales_group_code','like',$count.'%')->first();
      if ($max_partner_sales_auth){
        $max_number = substr($max_partner_sales_auth->sales_group_code,0,3);
        break;
      }  

    }

    for($i=$max_number;$i<999;$i++)   
    {   

      $count ='';
      $count = str_pad($i,3,"0",STR_PAD_LEFT);

      $partner_sales_auth = PartnerSalesAuth::where('sales_group_code','like',$count.'%')->first();
      if ($partner_sales_auth){
        continue;
      }
      if (!in_array($count,$choese_array)){
        $choese_array[]=$count;
      }
      if (count($choese_array)==3){
        break;
      }
    }  


    return $choese_array;
  }   
  //尚未檢查業務群裡面的人員
	protected function SessionFunction( $args=null ) : string {

    $message = '';
		$group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
    }
    $command = $args->command;
    if ( mb_substr($command,0,3) != '設業務' ){
      return '您當前會話尚未結束，請先完成設定';
    }
    if ( !isset($args->mention) ){
      return '您未tag任何人，請再次輸入';
    }

    $user = $args->user;
    $mentionees = $args->mention->mentionees;
		$session  = Redis::hgetall(md5($user->id));
		$msg_list = json_decode($session['msg_list'],true);
    
    if ( mb_substr($command,0,3) == '設業務' ){
      $command = str_replace(" ",'',$command);
      $partner_sales_auth_count = PartnerSalesAuth::where('sales_group_code','like',$session['sales_group_code'].'%')->get();
      if ( count($partner_sales_auth_count) + count($mentionees) > 10 ){
        return '業務群組不得超過9位業務，該業務群組目前已有'.count($partner_sales_auth_count).'位業務，當前欲設定'.count($mentionees).'位';
      }
      $index = count($partner_sales_auth_count);
      $sucess_setting_array = [];
      foreach ( $mentionees as $mention){
        if (!isset($mention->userId)){
          Redis::del(md5($user->id));
          $mention_array = [];
          foreach ( $mentionees as $mention){
            if ( isset($mention->userId) ){
              $mention_array[] = LineUser::where('id',$mention->userId)->first()->latest_name;
            }
          }
          $fail_name_array = array_diff($mention_array,$sucess_setting_array);
          if ( count($sucess_setting_array)==0 && count($fail_name_array)==0 ){
            $message = 'tag的'.mb_substr($command,$mention->index,$mention->length).'沒有設定line id，新增程序已中斷，其後tag的業務將不新增';
            return $message;
          }
 
          if ( count($sucess_setting_array)>0 ){
            $message .= "\n".'設定成功:'."\n";
            foreach ( $sucess_setting_array as $sucess_setting_name ){
              $message .= $sucess_setting_name."\n";
            }
          }
          if ( count($fail_name_array)>0 ){
            $message .= "\n".'未設定成功:'."\n";
            foreach ( $fail_name_array as $fail_setting_name ){
              $message .= $fail_setting_name;
              if ( end($fail_name_array)!=$fail_setting_name){
                $message .= "\n";
              }
            }
          }
          return $message;
        }

        $if_sales_exist = Sales::where('line_user_id',$mention->userId)->first();
        if ( !$if_sales_exist ){
          $sales = new Sales;
          $sales->line_user_id = $mention->userId;
          $sales->sn = 'S'.date('ymd').substr(time(),-5);
          $sales_save_result = $sales->save();
        }else{
          $sales_save_result = true;
          $sales = $if_sales_exist;
        }

        if ( $sales_save_result ){
          $if_partner_sales_exist = PartnerSalesAuth::where('sales_id',$sales->id)->where('partner_id',$session['partner_id'])->first();
          if (!$if_partner_sales_exist){
            $partner_sales_auth = new PartnerSalesAuth;
            $partner_sales_auth->partner_id = $session['partner_id'];
            $partner_sales_auth->sales_id = $sales->id;
            $partner_sales_auth->sales_group_code = $session['sales_group_code'].$index;
            $partner_sales_auth_save_result = $partner_sales_auth->save();
            if (!$partner_sales_auth_save_result){
              Redis::del(md5($user->id));
              $line_user = LineUser::where('id',$mention->userId)->first();
              $mention_array = [];
              foreach ( $mentionees as $mention){
                if ( isset($mention->userId) ){
                  $mention_array[] = LineUser::where('id',$mention->userId)->first()->latest_name;
                }
              }
              $fail_name_array = array_diff($mention_array,$sucess_setting_array);
              $message = '綁定業務'.$line_user->latest_name.'廠商時發生錯誤，新增程序已中斷，其後tag的業務將不新增';
              if ( count($sucess_setting_array)>0 ){
                $message .= "\n".'設定成功:'."\n";
                foreach ( $sucess_setting_array as $sucess_setting_name ){
                  $message .= $sucess_setting_name."\n";
                }
              }
              if ( count($fail_name_array)>0 ){
                $message .= "\n".'未設定成功:'."\n";
                foreach ( $fail_name_array as $fail_setting_name ){
                  $message .= $fail_setting_name;
                  if ( end($fail_name_array)!=$fail_setting_name){
                    $message .= "\n";
                  }
                }
              }
              return $message;
            }
          }
          else{
            Redis::del(md5($user->id));
            $line_user = LineUser::where('id',$mention->userId)->first();
            $mention_array = [];
            foreach ( $mentionees as $mention){
              if ( isset($mention->userId) ){
                $mention_array[] = LineUser::where('id',$mention->userId)->first()->latest_name;
              }
            }
            $fail_name_array = array_diff($mention_array,$sucess_setting_array);
            $message = '綁定業務'.$line_user->latest_name.'時發生錯誤，因為該帳號已為業務,故新增程序已中斷，其後tag的業務將不新增';
            if ( count($sucess_setting_array)>0 ){
              $message .= "\n".'設定成功:'."\n";
              foreach ( $sucess_setting_array as $sucess_setting_name ){
                $message .= $sucess_setting_name."\n";
              }
            }
            if ( count($fail_name_array)>0 ){
              $message .= "\n".'未設定成功:'."\n";
              foreach ( $fail_name_array as $fail_setting_name ){
                $message .= $fail_setting_name;
                if ( end($fail_name_array)!=$fail_setting_name){
                  $message .= "\n";
                }
              }
            }
            return $message;
          }
          $index++;
        }
        else{
          Redis::del(md5($user->id));
          $line_user = LineUser::where('id',$mention->userId)->first();
          $mention_array = [];
          foreach ( $mentionees as $mention){
            if ( isset($mention->userId) ){
              $mention_array[] = LineUser::where('id',$mention->userId)->first()->latest_name;
            }
          }
          $fail_name_array = array_diff($mention_array,$sucess_setting_array);

          $message = '綁定業務'.$line_user->latest_name.'時發生錯誤，新增程序已中斷，其後tag的業務將不新增';
          if ( count($sucess_setting_array)>0 ){
            $message .= "\n".'設定成功:'."\n";
            foreach ( $sucess_setting_array as $sucess_setting_name ){
              $message .= $sucess_setting_name."\n";
            }
          }
          if ( count($fail_name_array)>0 ){
            $message .= "\n".'未設定成功:'."\n";
            foreach ( $fail_name_array as $fail_setting_name ){
              $message .= $fail_setting_name;
              if ( end($fail_name_array)!=$fail_setting_name){
                $message .= "\n";
              }
            }
          }
          return $message;
        }
        $line_user = LineUser::where('id',$mention->userId)->first();
        $sucess_setting_array[] = $line_user->latest_name;

      }

      Redis::del(md5($user->id));

      //同時也請回傳在管理員群

      $message = '業務設定成功';
      if ( count($sucess_setting_array)>0 ){
        $message .= "\n".'設定成功:'."\n";
        foreach ( $sucess_setting_array as $sucess_setting_name ){
          $message .= $sucess_setting_name."\n";
        }
      }

      return $message;
    }
    else{
      return '您當前與機器人對話中，請完成您先前的設定手續，如:'.'設業務(空格)@某人';
    }
    
  }
  


}
