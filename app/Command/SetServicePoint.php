<?php
namespace App\Command;

use App\Model\Area;
use App\Model\ServicePoint;
use App\Line\ApiHelper;

class SetServicePoint extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new SetServicePoint();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '設據點',
			'cmd' => '設據點',
			'description' => '設據點資料，&設據點{據點名稱}(空格){廠商id}(空格){所在區域名稱}(空格){地址}({一字暱稱}，例如:&設據點東興 4 桃園 桃園市xxx路100號(東',
      'session_functions' => [
			],
			'access' => ['admin','group_admin'],
			'authorized_group_type' => ['Admin'],
		];
	}
		
	/* 實作 */
  protected function process( $args=null ) {

    $user = $args->user;
    $group = $args->group;
    $command = $args->command;
    if( !$group || $group->enble=='N' ){
      $message = "未授權";
      return config('app.debug') ? $message : null;
    }
    
    $message = "格式錯誤(E00),請使用以下格是:\n".
      $this->command_data['pre_command'].$this->command_data['cmd']."{據點名稱}(空格){廠商id}(空格){所在區域名稱}(空格){地址}({一字暱稱}，例如:&設據點東興 2 桃園 桃園市xxx路100號(東";
      
    $command = preg_replace('/\s+/',' ',$command);
    //去頭之後的內容
    $command = substr($command,strlen( $this->command_data['cmd']));
      
    $partner = $group->partners->first();
    if( !$partner )
      return "本群未綁定任何廠商";
    // if( $group->partners->count()>1 )
    //   return "本群綁定超過2個廠商";
    //去頭之後的內容

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
      return '您不具管理員身分，無法設據點';
    }
    $tmp = [];
    $tmp = explode(' ',$command);
    $service_point_name = $tmp[0];
    if ( !isset($tmp[1]) || empty($tmp[1]) ){
      return '請輸入據點所屬廠商id'.$this->command_data['description'];
    }
    else if( !is_numeric($tmp[1])){
      return '廠商id需為數字!'.$this->command_data['description'];
    }
    else if( !in_array($tmp[1],$partner_id_array) ){
      return '抱歉，您不具有廠商id'.$tmp[1].'之管理員身份';
    }
    $service_point_partner_id = $tmp[1];
    
    if ( !isset($tmp[2]) || empty($tmp[2]) ){
      return '請輸入所在區域名稱名稱'.$this->command_data['description'];
    }
    
    $area_name = $tmp[2];
    if ( $area_name == '下架' ){
      return '區域名稱為非法字眼';
    }
    if ( $service_point_name == '下架' || $service_point_name =='未分類' ){
      return '據點名稱為非法字眼';
    }
    $area = Area::where('name',$area_name)->first();
    if ( !$area ){
      $area_biggest = Area::where('id','<',999998)->orderBy('id', 'desc')->first();
      $area = new Area();
      $area->id = $area_biggest->id+1;
      $area->name = $area_name;
      $area->save();
    }
    $address = '';
    $nickname =  '';

    if (isset($tmp[3])){

      $temp = [];
      $temp = explode('(',$tmp[3]);
      if ( !isset($temp[1]) ){
        return '未輸入一字暱稱!'.$this->command_data['description'];
      }
      if ( $temp[1]=='下' || $temp[1]=='未' ){
        return '抱歉，暱稱 "下" 或者 "未" 為系統保留字，請換個暱稱再次嘗試';
      }
      if ( mb_strlen($temp[1])!=1 ){
        return '抱歉，括號後需為一字暱稱';
      }
      $is_nickname_exist = ServicePoint::where('nickname',$temp[1])->whereIn('partner_id',$partner_id_array)->first();
      if ( $is_nickname_exist ){
        return '抱歉，您輸入之一字暱稱已被使用，請換一個';
      }
      $nickname = $temp[1];
      $address = $temp[0];
      
    }
    else{
      return '請輸入據點地址';
    }
    $service_point_data = ServicePoint::where('area_id',$area->id)->where('name',$service_point_name)->first();
    if ( !$service_point_data ){
      $service_point_biggest = ServicePoint::where('id','<',999998)->orderBy('id', 'desc')->first();
      $service_point = new ServicePoint;
      $service_point->id = $service_point_biggest->id+1;
      $service_point->area_id = $area->id;
      $service_point->partner_id = $service_point_partner_id;
      $service_point->name = $service_point_name;
      $service_point->address = $address;
      $service_point->nickname = $nickname;
      $save_result = $service_point->save();
      if ( !$save_result ){
        return $area->id.'-'.$service_point_name.'據點新增失敗';
      }
      $message = '據點'.$service_point_name.'新增成功';
      return $message;
    }
    $update_result = ServicePoint::updateOrCreate(
      ['area_id'=>$area->id,'name'=>$service_point_name],
      ['address'=>$address,'nickname'=>$nickname,'partner_id'=>$service_point_partner_id]
    );
    if ( !$update_result ){
      return $area->id.'-'.$service_point_name.'據點更新失敗';
    }
    $message = '據點'.$service_point_name.'啟用或更新成功';

    $message = trim($message,"\n");


    return $message;
  }

  protected function SessionFunction( $args=null ){

  }

}
