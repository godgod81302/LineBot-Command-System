<?php
namespace App\Command;

use App\Model\Server;
use App\Line\ApiHelper;
use App\Model\Image;
use App\Model\RoomData;
use App\Model\ServicePoint;
use App\Model\RoomImgPair;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
class SetRoomImg extends BaseCommand{
  
  private static $instance;
  
  public static function getInstance(){
    if( !self::$instance ){
      self::$instance = new SetRoomImg();
    }
    return self::$instance;
  }
  
  private function __construct(){
    $this->command_data = [
      'pre_command' => '&',
      'name' => '設房照',
      'cmd' => '設房照',
      'description' => '設房照{據點暱稱+房號}(空格){所屬廠商id}',
      'session_functions' => [
        'setImg',
      ],
      'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
      'reply_questions' => ['請上傳導引上照片，(若上傳完導引上照片，請輸入點號 . 以繼續上船房號圖)'],
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
    //可以的話先return200，避免超時 上線前加上

    $message = "格式錯誤(E00),請使用以下格是:\n".
      $this->command_data['pre_command'].$this->command_data['cmd']."{據點暱稱+房號}(空格){所屬廠商id}";
      
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
      return '您不具管理員身分，無法設照片';
    }

    $tmp = [];
    $tmp = explode(' ',$command);
    if (empty($tmp[0])){
      return $message;
    }
    if ( preg_match('/^\p{Han}/u',$tmp[0],$temp) ){
      $service_point_nickname = $temp[0];
      $room_number = mb_substr($command,1,mb_strlen($tmp[0])-1);
    }
    else{
      return $message;
    }
    if ( !isset($tmp[1]) ){
      $service_points = ServicePoint::where('nickname',$service_point_nickname)->whereIn('partner_id',$partner_id_array)->get();
      if (count($service_points)>1){
        return '有多個一字暱稱為'.$service_point_nickname.'的據點，請於廠商房間號後接上指定之廠商id，如:&設房照{據點暱稱+房號}(空格){所屬廠商id}';
      }
      if ( count($service_points)<1 ){
        return '抱歉，暱稱為:'.$service_point_name.'之據點不存在';
      }
      $service_point = $service_points->first();
    }
    else{

      if( !is_numeric($tmp[1])){
        return '廠商id需為數字!'.$this->command_data['description'];
      }
      else if( !in_array($tmp[1],$partner_id_array) ){
        return '抱歉，您不具有廠商id'.$tmp[1].'之管理員身份';
      }
      $service_point = ServicePoint::where('nickname',$service_point_nickname)->where('partner_id',$tmp[1])->first();
      if ( !$service_point ){
        return '抱歉，暱稱為:'.$service_point_name.'，且屬於廠商id為:'.$tmp[1].'之據點不存在';
      }

    }

    $room_data = RoomData::where('service_point_id',$service_point->id)->where('number',$room_number)->first();
    if ( !$room_data ){
      return '抱歉，房間'.$room_number.'不存在';
    }
    
    Redis::del(md5($user->id.$group->id));

    Redis::hmset(md5($user->id.$group->id),'timestamp',strtotime('now'),'classname',__CLASS__,'service_point_id',$service_point->id,'room_data_id',$room_data->id,'room_number',$room_number);
    // $room_img_pairs = RoomImgPair::where('room_data_id',$is_server_exist->id)->get();
    // foreach ( $room_img_pairs as $room_img_pair ){
    //   $image_url = Image::where("id",$room_img_pair->image_id)->first()->image_url;
    //   Image::where('id',$room_img_pair->image_id)->delete();
    //   RoomImgPair::where('id',$room_img_pair->id)->delete();
    //   $image_data = [];
    //   $image_data = explode("/",$image_url);
    //   $url = Storage::disk($image_data[0])->delete($image_data[1]);
    // }
    $message = $this->command_data['reply_questions'][0];
    $message = trim($message,"\n");
    $continue_message = "\n".'或輸入 #{數字} 執行功能以繼續'."\n".'#1查照#2刪照#3完成';

    return $message.$continue_message;
  }
  protected function SessionFunction( $args=null ){

    $group = $args->group;
    if( !$group || $group->enble=='N' ){
      $message = "未授權";
      return $message;
    }
    if (function_exists("fastcgi_finish_request")) { 
			fastcgi_finish_request();
		}
    $command = $args->command;
    $continue_message = "\n".'請輸入 #{數字} 執行功能以繼續'."\n".'#1查照#2刪照#3完成';
    $user = $args->user;
    $redis_con = Redis::connection();
    $redis = (object)$redis_con->hgetall(md5($user->id.$group->id));
    // if ( !isset($redis) )$redis_con->hset(md5($user->id.$group->id),'redis_lock',false);
    //Line傳來的圖通常帶純數id，只是小防一下不合法上傳
    if ( $args->type == 'image' ){
       if ( !is_numeric($command) ){
          return '圖片上傳有誤，請上傳房間圖片';
       }
       if ( !isset($redis->img_count) ){
        $redis_con->hmset(md5($user->id.$group->id),'img_count',0,'img_for','checkpoint');
        $redis = (object)$redis_con->hgetall(md5($user->id.$group->id));
       }
    }
    else{
        if ($command=='#1'){
        $room_img_pairs = RoomImgPair::where('room_data_id',$redis->room_data_id)->where('img_for','checkpoint')->offset(0)->limit(5)->get();
        $messages = [];
        foreach (  $room_img_pairs as  $room_img_pair ){
          $object = (object)[];
          $object->type = 'image';
          $image_url = Image::where("id",$room_img_pair->image_id)->first()->image_url;
          $image_data = [];
          $image_data = explode("/",$image_url);
          $url = Storage::disk($image_data[0])->url($image_data[1]);
          $object->originalContentUrl = $url;
          $object->previewImageUrl = $url;
          $messages[] = $object;
        }
        if (count($messages)==0){
          $messages = '目前無導引上照片'.$continue_message;
        }
        else{
          array_push($messages,'以上為導引上照片，請輸入任一字以繼續取得房號圖');
          $redis_con->hset(md5($user->id.$group->id),'get_room_img',true);
        }
        return $messages;
      }
      else if($command=='#2'){
        $room_img_pairs = RoomImgPair::where('room_data_id',$redis->room_data_id)->get();
        foreach (  $room_img_pairs as  $room_img_pair ){
          $image_url = Image::where("id",$room_img_pair->image_id)->first()->image_url;
          $image_data = [];
          $image_data = explode("/",$image_url);
          $url = Storage::disk($image_data[0])->delete($image_data[1]);
          Image::where("id",$room_img_pair->image_id)->delete();
        }
        RoomImgPair::where('room_data_id',$redis->room_data_id)->delete();
        $redis_con->hset(md5($user->id.$group->id),'redis_lock',false);
        $redis_con->hdel(md5($user->id.$group->id),'img_count');
        $redis_con->hdel(md5($user->id.$group->id),'img_for');
        return '該房圖片已全部刪除';
      }
      else if ($command=='#3'){
        $redis_con->del(md5($user->id.$group->id));
        return '對話已關閉';
      }
      else if( isset($redis->get_room_img) && $redis->get_room_img ){
        $redis_con->hset(md5($user->id.$group->id),'get_room_img',false);
        $room_img_pairs = RoomImgPair::where('room_data_id',$redis->room_data_id)->where('img_for','room')->offset(0)->limit(5)->get();
        $messages = [];
        foreach (  $room_img_pairs as  $room_img_pair ){
          $object = (object)[];
          $object->type = 'image';
          $image_url = Image::where("id",$room_img_pair->image_id)->first()->image_url;
          $image_data = [];
          $image_data = explode("/",$image_url);
          $url = Storage::disk($image_data[0])->url($image_data[1]);
          $object->originalContentUrl = $url;
          $object->previewImageUrl = $url;
          $messages[] = $object;
        }
        if (count($messages)==0){
          $messages = '目前無房號圖照片，請上傳圖片'.$continue_message;
        }
        else{
          array_push($messages,'以上為房號圖照片'.$continue_message);
        }
        return $messages;
      }
      else if( isset($redis->img_count) && $command =='.'){
        $redis_con->hset(md5($user->id.$group->id),'img_for','room');
        return '請繼續上傳房號圖';
      }
      else{
        return '您的對話尚未結束，請繼續上傳照片'.$continue_message;
      }

    }
    while ( $redis_con->hget(md5($user->id.$group->id),'redis_lock') && ($redis_con->hget(md5($user->id.$group->id),'lock_time') > (time()-10) ) ){
      usleep(10000);
    }
    CommandUtil::lockRedis(md5($user->id.$group->id));
    if ( $redis->img_count == 0 ){
      $room_img_pairs = RoomImgPair::where('room_data_id',$redis->room_data_id)->get();
      foreach (  $room_img_pairs as  $room_img_pair ){
        $image_url = Image::where("id",$room_img_pair->image_id)->first()->image_url;
        $image_data = [];
        $image_data = explode("/",$image_url);
        $url = Storage::disk($image_data[0])->delete($image_data[1]);
        Image::where("id",$room_img_pair->image_id)->delete();
      }
      RoomImgPair::where('room_data_id',$redis->room_data_id)->delete();
    }
    $img_for = $redis->img_for;
    if ( ($redis->img_count > 1) && $img_for!='room'){
      return '抱歉!導引上照片已滿兩張，請輸入點號 . 以繼續上傳房號圖';
    }

    $room_img_count = RoomImgPair::where('room_data_id',$redis->room_data_id)->count();
    if ( $room_img_count > 4 ){
      CommandUtil::unlockRedis(md5($user->id.$group->id));
      return '該房間照片已滿五張，無法繼續新增'.$continue_message;
    }



    $result =  $this->setImg($command,$redis->room_data_id);
    if (!$result->result){
      return $result->data;
    }
    $images = new Image;
    $images->image_url=$result->disk_name.'/'.$result->data;
    $image_save_result = $images->save();
    if ( $image_save_result ){
      $room_images = new RoomImgPair ;
      $room_images->room_data_id = $redis->room_data_id;
      $room_images->image_id = $images->id;
      $room_images->img_for = $img_for;
      $room_images_pair_result = $room_images->save();
      if ( $room_images_pair_result ){
        CommandUtil::unlockRedis(md5($user->id.$group->id));
        $redis_con->hset(md5($user->id.$group->id),'img_count',$redis->img_count+1);
        if ( $img_for =='checkpoint'){
          $suceessed_messagee = '導引上';
        }
        else{
          $suceessed_messagee = '房號圖';
        }
        $suceessed_messagee .= '圖片新增成功';
        if($redis->img_count==1){
          $suceessed_messagee .= '，已傳完導引上圖'."\n".'(請輸入點號 . 以繼續上傳房號圖)';
        }
        if($redis->img_count==4){
          $suceessed_messagee .= '，已傳完房號圖';
        }
        return '房間'.$redis->room_number.$suceessed_messagee.$continue_message;
      }
      else{
        CommandUtil::unlockRedis(md5($user->id.$group->id));
      return '房間圖片綁定配對失敗';
      }
    }
    else{
      CommandUtil::unlockRedis(md5($user->id.$group->id));
      return '房間圖片新增置資料庫失敗';
    }

  }

  private function setImg($photo_id,$room_data_id){
    $Result = [
      'result'=>false,
      'data'=>'',
    ];
    $helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
    $image_name = 'r'.$room_data_id.'_'.microtime(true).'.jpg';
    Storage::disk('Room_images')->put($image_name, $helper->getContent($photo_id));
    $photo_exist = Storage::disk('Room_images')->exists($image_name);

    if ( $photo_exist ){
      $Result['result']=true;
      $Result['data']=$image_name;
      $Result['disk_name']='Room_images';
    }
    else{
      $Result['data']='圖片上傳失敗，請嘗試重新上傳，或聯繫系統工程師';
    }
    return (object)$Result;
  }

}
