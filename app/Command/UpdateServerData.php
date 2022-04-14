<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\Server;
use App\Model\Area;
use App\Model\Country;
use App\Model\Service;
use App\Model\Broker;
use App\Model\ServiceList;
use App\Model\RoomData;
use App\Model\ServicePoint;
use App\Model\RoomServerPair;

class UpdateServerData extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new UpdateServerData();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '改服務員',
			'cmd' => '改服務員',
			'description' => '查服務員，格式為:&改服務員'."\n".
      'name:{服務員名稱}'."\n".
      'partner_id:{廠商代號}'."\n".
      'broker_id:{經紀人id}'."\n".
      '房號:{房號名稱}'."\n".
      '國籍:{國籍名}'."\n".
      '語言:{語系名稱}'."\n".
      '身體資訊:{身高.體重.罩杯.年紀}'."\n".
      '服務類型:{定點或外送}'."\n".
      '特色標籤:{服務員特色，如:幼嫩/巨乳/口爆/無套...}'."\n".
      '方案:'."\n".'{(分鐘/次/妹拿/經濟拿/回價) 例如:30/1/1100/3000/2000}'."\n".
      '特殊服務:'."\n".'{服務名稱:價位,如=> 奶泡:0}'
      ,

			'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
	  $message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."\n".
      'name:{服務員名稱}'."\n".
      'partner_id:{廠商代號}'."\n".
      'broker_id:{經紀人id}'."\n".
      '房號:{房號名稱}'."\n".
      '國籍:{國籍名}'."\n".
      '語言:{語系名稱}'."\n".
      '身體資訊:{身高.體重.罩杯.年紀}'."\n".
      '服務類型:{定點或外送}'."\n".
      '特色標籤:{服務員特色，如:幼嫩/巨乳/口爆/無套...}'."\n".
      '方案:'."\n".'{(分鐘/次/妹拿/經濟拿/回價) 例如:30/1/1100/3000/2000}'."\n".
      '特殊服務:'."\n".'{服務名稱:價位,如=> 奶泡:0}';
		if( strpos($args->command, $this->command_data['cmd'])!==0 )
			return $message;

		$message = "";
    $user = $args->user;
		$group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}
    $partner = $group->partners->first();
    if( !$partner )
      return "本群未綁定任何廠商";
    $admin_access = false;
    $super_admin_acess = false;
    $partner_id_array = [];
    foreach( $user->group_admins as $admin ){
      $partner_id_array[] = $admin->partner->id;
      if( $admin->partner->id==$partner->id ){
        $admin_access = true;
        break;
      }
      else if ( $admin->partner->id == 1 ){
        $super_admin_acess = true;
        break;
      }
    }

    if ( !$admin_access && !$super_admin_acess ){
      return '您不具管理員身分，無法設照片';
    }
		$command = $args->command;
		$user = $args->user;

		//過濾指令字
    $command_msg = substr($command, strlen($this->command_data['cmd']));
    $command_msg = str_replace(" ",'',$command_msg);

    $parsing_result = $this->breakLineParsing($command_msg,$partner_id_array,$admin_access,$super_admin_acess);

    if (!$parsing_result->is_legal){
      $parsing_result->message = trim($parsing_result->message,"/");
      return $parsing_result->message;
    }
    else{
      if (!empty($parsing_result->message)){
        $message .= $parsing_result->message."\n";
      }
    }
    $server = Server::where('name',$parsing_result->name)->where('partner_id',$parsing_result->partner_id)->first();

    $update_array=[];
    // $server_basic_info_colums = ['broker_id','country_id','area_id','lanague','height','weight','cup','age','service_type','description','enable'];
    $server_basic_info_colums = ['broker_id','country_id','lanague','height','weight','cup','age','service_type','description','enable'];
    foreach( $server_basic_info_colums as $colum ){
      if (isset($parsing_result->$colum)){
        if ($server->$colum!=$parsing_result->$colum){
          $update_array[$colum] = $parsing_result->$colum;
        }
      }
    }

    $update_result = Server::updateOrCreate(
      ['name'=>$parsing_result->name,'partner_id'=>$parsing_result->partner_id],
      $update_array
    );

    if (!$update_result){
      $message = '資料更新失敗';
    }

    if ( isset($parsing_result->service_array) && !empty($parsing_result->service_array)){
      $service_info=[];
      $db_services = Service::where('server_id',$server->id)->where('description','<>','特殊服務')->orderBy('period')->get();
      foreach( $db_services as $db_service ){
        $service_info[]=$db_service->period.'/'.$db_service->s_time;
      }
      foreach( $parsing_result->service_array as $index => $parsing_service ){
        $update_service = [];
        $temp_parsing_service = [];
        $temp_parsing_service = explode('/',$parsing_service);
        $update_service['server_fee']=$temp_parsing_service[2];
        $update_service['broker_fee']=$temp_parsing_service[3];
        $update_service['company_profit']=$temp_parsing_service[4]-$temp_parsing_service[2]-$temp_parsing_service[3];
        if ($temp_parsing_service[0] >30){
          $update_service['description']='長時服務';
          $update_service['name']='long_service';
        }
        else{
          $update_service['description']='短時服務';
          $update_service['name']='short_service';
        }

        if ($update_service['company_profit']<200){
          return $parsing_service.'店利不得小於200元';
        }
        $update_service_result = Service::updateOrCreate(
          ['server_id'=>$server->id,'period'=>$temp_parsing_service[0],'s_time'=>$temp_parsing_service[1]],
          $update_service
        );
        if ($update_service_result){
          if ( in_array($temp_parsing_service[0].'/'.$temp_parsing_service[1],$service_info) ){
            array_splice($service_info,array_search($temp_parsing_service[0].'/'.$temp_parsing_service[1],$service_info),1);
          }
        }
      }
      //刪除沒提及的方案
      if (count($service_info)>0){
        foreach ( $service_info as $delete_s_time ){
          $temp_delete = [];
          $temp_delete = explode("/",$delete_s_time);
          Service::where('server_id',$server->id)->where('period',$temp_delete[0])->where('s_time',$temp_delete[1])->delete();
        }
      }
      //end if
    }
    $db_special_services = Service::where('server_id',$server->id)->where('description','特殊服務')->orderBy('period')->pluck("name")->toArray();

    if ( isset($parsing_result->special_service_array)){
      foreach( $parsing_result->special_service_array as $special_service ){
        $update_service =[];
        $temp_special_service = [];
        $temp_special_service = explode(":",$special_service);
        $update_service['server_fee']=$temp_special_service[1];
        $update_service_result = Service::updateOrCreate(
          ['server_id'=>$server->id,'name'=>$temp_special_service[0],'description'=>'特殊服務','period'=>0],
          $update_service
        );
        if ( count($db_special_services)>0 ){
          array_splice($db_special_services,array_search($temp_special_service[0],$db_special_services),1);
        }
      }
      if ( count($db_special_services)>0 ){
        foreach( $db_special_services as $delete_special_service ){
          Service::where('server_id',$server->id)->where('description','特殊服務')->where('name',$delete_special_service)->delete();
        }
      }
  
    }


    $server_new = Server::where('name',$parsing_result->name)->where('partner_id',$parsing_result->partner_id)->first();
    $message .= '基本資料更新成功'."\n"."\n".$this->serverData($server_new);


		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
  }

  private function breakLineParsing($command_msg,$partner_id_array,$admin_access=false,$super_admin_acess=false){

    $result = (object)[];
    $result->is_legal = false;
    $result->message='';
    $break_line_list = [];
		$break_line_list = explode("\n",$command_msg);
    $service_array = [];
    $special_service_array = [];
    $delete_special_service_array = [];
    foreach( $break_line_list as $index => $break_line ){
      $tmp = [];
      $tmp = explode(':',$break_line);
      if ($tmp[0]=='name' && isset($tmp[1])){
        //過濾中英文字串
        if( preg_match('/[\x{4e00}-\x{9fa5}A-Za-z_]*[\x{3105}-\x{3129}]*$/u',$tmp[1]) ){
          // 搜尋到的漢字在指令最前頭
          $result->name = $tmp[1];
        }
        else{
          $result->message .= '服務員名稱須為中文或英文/';
        }
      }
      if ($tmp[0]=='啟用' && isset($tmp[1])){
        //過濾中英文字串
        if( $tmp[1] == 'Y' || $tmp[1] =='N' ){
          // 搜尋到的漢字在指令最前頭
          $result->enable = $tmp[1];
        }
        else{
          $result->message .= '啟用須為大寫Y或N/';
        }
      }
      if ($tmp[0]=='partner_id' && isset($tmp[1])){
        //過濾中英文字串
        if( preg_match('/[0-9]+$/u',$tmp[1]) ){
          // 搜尋到的漢字在指令最前頭
          $result->partner_id = $tmp[1];
          if ( !in_array($tmp[1],$partner_id_array) ){
            $result->message .= '您不具有此服務員之管理權/';
          }
        }
        else{
          $result->message .= '廠商id須為數字/';
        }
      }
      if ($tmp[0]=='broker_id' && isset($tmp[1])){
        //過濾中英文字串
        if( preg_match('/[0-9]+$/u',$tmp[1]) ){
          $result->broker_id = $tmp[1];
        }
        else{
          $result->message .= '經紀人id須為數字/';
        }
      }
      // if ($tmp[0]=='區域' && isset($tmp[1])){
      //   //過濾中英文字串
      //   if( preg_match('/[\x{4e00}-\x{9fa5}]+$/u',$tmp[1]) ){
      //     $result->area_name = $tmp[1];
      //   }
      //   else{
      //     $result->message .= '區域須為中文/';
      //   }
      // }
      if ($tmp[0]=='據點' && isset($tmp[1])){
        $result->message .= '抱歉!目前已廢棄據點此選項，請將據點暱稱加在欲綁定之房號前/';
      }
      if ($tmp[0]=='房號' && isset($tmp[1])){
        $is_force = false;
        if ( mb_substr($tmp[1],-1) == '*' ){
          $is_force = true;
          $tmp[1] = mb_substr($tmp[1],0,-1);
        }
        $result->room_data_number = $tmp[1];
      }
      if ($tmp[0]=='國籍' && isset($tmp[1])){
        //過濾中英文字串
        if( preg_match('/[\x{4e00}-\x{9fa5}]+$/u',$tmp[1]) ){
          $result->country_name = $tmp[1];
        }
        else{
          $result->message .= '國籍須為中文/';
        }
      }
      if ($tmp[0]=='語言' && isset($tmp[1])){
        $tmp[1] = str_replace("/",',',$tmp[1]);
        $tmp[1] = str_replace(".",',',$tmp[1]);
        $lanagues = ['英文','中文','泰文'];
        //過濾中英文字串
        $temp_lanagues = [];
        $temp_lanagues = explode(',',$tmp[1]);
        foreach($temp_lanagues as $temp_lanague ){
          if ( !in_array($temp_lanague,$lanagues) ){
            $result->message .= $temp_lanague.'並非預設語言之一/';
          }
        }
        $result->lanague = $tmp[1];
      }
      if ($tmp[0]=='身體資訊' && isset($tmp[1])){
        $temp_body_info = [];
        $temp_body_info = explode('.',$tmp[1]);
        if( !preg_match('/[0-9]+$/u',$temp_body_info[0]) ){
          $result->message .= '身體資訊之身高須為數字/';
        }
        if( !preg_match('/[0-9]+$/u',$temp_body_info[1]) ){
          $result->message .= '身體資訊之體重須為數字/';
        }
        if( !preg_match('/[A-Za-z]+$/u',$temp_body_info[2]) ){
          $result->message .= '身體資訊之罩杯須為英文/';
        }
        if( !preg_match('/[0-9]+$/u',$temp_body_info[3]) ){
          $result->message .= '身體資訊之年紀須為數字/';
        }

        $result->height = $temp_body_info[0];
        $result->weight = $temp_body_info[1];
        $result->cup = strtoupper($temp_body_info[2]);
        $result->age = $temp_body_info[3];
      }
      if ($tmp[0]=='服務類型' && isset($tmp[1])){
        $catelog = ['定點','外送','按摩'];
        $temp_catelogs =[];
        $temp_catelogs = explode('/',$tmp[1]);

        foreach($temp_catelogs as $temp_catelog ){
          if ( !in_array($temp_catelog,$catelog) ){
            $result->message .= $temp_catelog.'並非預設服務類型之一/';
          }
        }
        $result->service_type = str_replace("/",",",$tmp[1]);

        if( preg_match('/^[\x{4e00}-\x{9fa5}]+$/u',$tmp[1]) ){
          $result->service_type = $tmp[1];
        }
        else{
          $result->message .= '服務類型須為中文/';
        }
      }
      if ($tmp[0]=='特色標籤' && isset($tmp[1])){
        $temp_description = [];
        $temp_description =  explode('/',$tmp[1]);
        $result->description = json_encode(['special_tags'=>$temp_description]);
      }
      if (mb_substr($tmp[0],0,2)=='方案'){
        for ( $i=$index+1;isset($break_line_list[$i]);$i++ ){
          $temp_service =[];
          $temp_service = explode('/',$break_line_list[$i]);
          if ( !isset($temp_service[4]) || !is_numeric($temp_service[0]) || !is_numeric($temp_service[1]) || !is_numeric($temp_service[2]) || !is_numeric($temp_service[3]) || !is_numeric($temp_service[4]) ){
            if (isset($temp_service[1])){
              $result->message .= '方案'.$break_line_list[$i].'格式錯誤，請依序輸入 分鐘/次/妹拿/經濟拿/回價 /';
            }
            continue;
          }
          $service_array[]=$break_line_list[$i];
        }
        $result->service_array = $service_array;

      }
      if (mb_substr($tmp[0],0,4)=='特殊服務'){
        for ( $i=$index+1;isset($break_line_list[$i]);$i++ ){
          $temp_special_service =[];
          $temp_special_service = explode(':',$break_line_list[$i]);
          if ( !isset($temp_special_service[1])  ){
            continue;
          }
          if (  !is_numeric($temp_special_service[1]) ){
            $result->message .= '請依格式填入( {特殊服務品項}={價格數字}) 例如: 毒龍=500/';
          }
          $special_name = $temp_special_service[0];
          $special_price = $temp_special_service[1];
          $if_service_exist = ServiceList::where('name',$special_name)->first();
          if (!$if_service_exist){
            $result->message .= '特殊服務'.$temp_special_service[0].'不存在/';
            continue;
          }

          $special_service_array[]=$special_name.':'.$special_price;
        }
        $result->special_service_array = $special_service_array;
      }

    }
    
    //上面檢測完資料正確性，這邊來檢測資料庫裡是否有對應資料
    $is_server_exist = Server::where('name',$result->name)->where('partner_id',$result->partner_id)->first();
    if ( !$is_server_exist ){
      $result->message .= '該服務員不存在/';
    }

    $if_partner_exist = Partner::where('id',$result->partner_id)->first();
    if ( !$if_partner_exist ){
      $result->message .= '該廠商不存在/';
    }
    if (isset($result->broker_id)){
      $if_broker_exist = Broker::where('id',$result->broker_id)->first();
      if ( !$if_broker_exist ){
        $result->message .= '該經濟不存在/';
      }
    }
    // if (isset($result->area_name)){
    //   $if_area_exist = Area::where('name',$result->area_name)->first();
    //   if ( !$if_area_exist ){
    //     $result->message .= '該區域不存在/';
    //   }
    //   if ($result->area_name == '下架' ){
    //     $result->enable = 'N' ;
    //   }
      
    //   $result->area_id = $if_area_exist->id;
    // }
    if (isset($result->country_name)){
      $if_country_exist = Country::where('name',$result->country_name)->first();
      if ( !$if_country_exist ){
        $result->message .= '該國籍不存在/';
      }
      $result->country_id = $if_country_exist->id;
    }
    if (!empty($result->message)){
      return $result;
    }

    if ( isset($result->room_data_number) ){
        $service_point_nickname = mb_substr($result->room_data_number,0,1);
        $result->room_data_number = mb_substr($result->room_data_number,1,mb_strlen($result->room_data_number)-1);
        $is_server_has_a_room = RoomServerPair::where('server_id',$is_server_exist->id)->first();
        // if ( $is_server_has_a_room ){
        //   if ( !$is_force ){
        //     $room_data = RoomData::where('id',$is_server_has_a_room->room_data_id)->first();
    
        //     $result->message .= '服務員:'.$is_server_exist->name.'已經綁定房號'.$room_data->number.'，若要改綁，請再房號結尾加上*表示強制，而前綁定將解除/';
        //     return $result;
        //   }
        //   else{
        //       RoomServerPair::where('server_id',$is_server_exist->id)->delete();
        //   }
        // }
        $is_service_point_exist = ServicePoint::where('nickname',$service_point_nickname)->where('partner_id',$if_partner_exist->id)->first();
        if (!$is_service_point_exist){
          if ( $service_point_nickname != '下' ){
            $result->message .= '房號第一字為據點暱稱，而暱稱:'.$service_point_nickname.'之據點不存在';
            return $result;
          }
          else{
            $is_service_point_exist = ServicePoint::where('nickname',$service_point_nickname)->first();
          }
        }
        if ( $service_point_nickname == '下' ){
          $result->enable = 'N';
        }
        else{
          $result->enable = 'Y';
        }
        $is_room_exist = RoomData::where('service_point_id',$is_service_point_exist->id)->where('number',$result->room_data_number)->first();
        if ( !$is_room_exist ){
          $result->message .= $is_service_point_exist->name.'據點下之房間'.$result->room_data_number.'不存在';
          return $result;
        }
        if ( $is_room_exist->enable=='N' ){
          $result->message .= $is_service_point_exist->name.'據點下之房間'.$result->room_data_number.'未開放';
          return $result;
        }
        $room_server_pair = RoomServerPair::where('room_data_id',$is_room_exist->id)->first();
        if ( $room_server_pair ){
          // if ( $is_force ){
            // if ( !$super_admin_acess ){
            //   $pre_server_partner_id = Server::where('id',$room_server_pair->server_id)->first()->partner_id;
            //   if ( !in_array($pre_server_partner_id,$partner_id_array) ){
            //     $result->message .= '該房間已有服務員，且您不具備該服務員之管理權，如仍須更動，請聯繫系統工程師';
            //     return $result;
            //   }
              
            // }
          //   $update_result = RoomServerPair::where('room_data_id',$is_room_exist->id)->update(['server_id'=>$is_server_exist->id]);
          //   if ($update_result){
          //     $pre_server_name = Server::where('id',$room_server_pair->server_id)->first()->name;
          //     $result->message .= '房號'.$result->room_data_number.'，服務員已由:'.$pre_server_name.'改綁為:'.$is_server_exist->name;
          //     return $result;
          //   }
          // }
          // else{
          //   $result->message .= '房間已由其他服務員綁定，若要改綁，請再結尾加上*表示強制，而前綁定將解除';
          //   return $result;
          // }
          $is_server_pair_target_room = RoomServerPair::where('room_data_id',$is_room_exist->id)->where('server_id',$is_server_exist->id)->first();
          if ( !$is_server_pair_target_room ){
            RoomServerPair::where('server_id',$is_server_exist->id)->delete();
            $room_server = new RoomServerPair;
            $room_server->server_id = $is_server_exist->id;
            $room_server->room_data_id = $is_room_exist->id;
            $save_result = $room_server->save();
            if ($save_result){
              $pre_server_name = Server::where('id',$room_server_pair->server_id)->first()->name;
              $result->is_legal = true;
              $result->message .= '提醒!房號'.$is_service_point_exist->nickname.$result->room_data_number.'，之服務員已有:'.$pre_server_name.'，同時也已綁定服務員:'.$is_server_exist->name;
              return $result;
            }
          }
        }
        else{
          RoomServerPair::where('server_id',$is_server_exist->id)->delete();
          $room_server = new RoomServerPair;
          $room_server->server_id = $is_server_exist->id;
          $room_server->room_data_id = $is_room_exist->id;
          $save_result = $room_server->save();
          if ($save_result){
            //代感
            $result->is_legal = true;
            // $result->message .= '房號'.$result->room_data_number.'，已綁定為'.$is_server_exist->name;
            return $result;
          }
        }

    }

    $result->is_legal = true;
    return $result;


  }





  private function serverData($server){
    $message = '';
    $message .= 'name:'.$server->name."\n";
    $message .= 'partner_id:'.$server->partner_id."\n";
    $message .= 'broker_id:'.$server->broker_id."\n";
    // $area = Area::where('id',$server->area_id)->first();
    // $message .= '區域:'.$area->name."\n";
    $room_server_pair = RoomServerPair::where('server_id',$server->id)->first();
    if ( $room_server_pair ){
      $room_data = RoomData::where('id',$room_server_pair->room_data_id)->first();
      if ( $room_data ){
        $service_point = ServicePoint::where('id',$room_data->service_point_id)->first();
        if ( $service_point ){
          // $message .=  '據點:'.$service_point->name."\n";
          $message .=  '房號:'.$service_point->nickname.$room_data->number."\n";
        }
      }
    }
    $contry = Country::where('id',$server->country_id)->first();
    $message .= '國籍:'.$contry->name."\n";
    $message .= '語言:'.$server->lanague."\n";
    $message .= '身體資訊:'.$server->height.'.'.$server->weight.'.'.$server->cup.'.'.$server->age."\n";
    $message .= '服務類型:'.$server->service_type."\n";
    $description_array = json_decode($server->description,true);
    if ( isset($description_array['special_tags'])){
      $message .= '特色標籤:';
      foreach ( $description_array['special_tags'] as $tag ){
        if ( $tag!=end($description_array['special_tags'])){
          $message .= $tag.'/';
        }
        else{
          $message .= $tag;
        }
      }
      $message .= "\n";
    }
    $services = Service::where('server_id',$server->id)->where('description',"<>",'特殊服務')->orderBy('period')->get();
    if ( count($services)>0 ){
      //{分鐘}/{次數}/{妹拿}/{經濟拿}/{店利}
      $message .= '方案(分鐘/次/妹拿/經濟拿/回價):'."\n";
      foreach( $services as $service ){
        $basic_price=$service->server_fee
        +$service->broker_fee
        -$service->company_cost
        +$service->company_profit
        -$service->marketing_cost
        +$service->sales_profit;

        $message .= $service->period.'/'.$service->s_time.'/'.$service->server_fee.'/'.$service->broker_fee.'/'.$basic_price."\n";
      }
    }
    //特別服務
    $special_services = Service::where('server_id',$server->id)->where('description','特殊服務')->orderBy('name')->get();
    if ( count($special_services)>0 ){
      $message .= '特殊服務:'."\n";
      foreach( $special_services as $special_service ){
        if (!preg_match("/[\x7f-\xff]/", $special_service->name)) {
          continue;
        }
        $message .= $special_service->name.':'.$special_service->server_fee."\n";
      }
      foreach( $special_services as $special_service ){
        if (!preg_match("/[\x7f-\xff]/", $special_service->name)) {
          $message .= $special_service->name.':'.$special_service->server_fee."\n";
        }
        else{
          continue;
        }
        }

    }
    return $message;
  }
}
