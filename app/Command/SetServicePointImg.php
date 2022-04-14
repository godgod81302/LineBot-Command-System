<?php
namespace App\Command;

use App\Line\ApiHelper;
use App\Model\Image;
use App\Model\ServicePoint;
use App\Model\ServicePointImgPair;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
class SetServicePointImg extends BaseCommand{
  
  private static $instance;
  
  public static function getInstance(){
    if( !self::$instance ){
      self::$instance = new SetServicePointImg();
    }
    return self::$instance;
  }
  
  private function __construct(){
    $this->command_data = [
      'pre_command' => '&',
      'name' => '設約客照',
      'cmd' => '設約客照',
      'description' => '設約客照{據點名}(空格){廠商id}',
      'session_functions' => [
        'setImg',
      ],
      'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
      'reply_questions' => ['請上傳約客照片'],
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
      $this->command_data['pre_command'].$this->command_data['cmd']."{據點名}(空格){廠商id}";
      
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
    $service_point_name = $tmp[0];

    if ( !isset($tmp[1]) ){
      $service_points = ServicePoint::where('name',$service_point_name)->whereIn('partner_id',$partner_id_array)->get();
      if (count($service_points)>1){
        return '有多個暱稱為'.$service_point_name.'的據點，請於廠商房間號後接上指定之廠商id，如:&設房照{據點暱稱+房號}(空格){所屬廠商id}';
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
      $service_point = ServicePoint::where('name',$service_point_name)->where('partner_id',$tmp[1])->first();
      if ( !$service_point ){
        return '抱歉，暱稱為:'.$service_point_name.'，且屬於廠商id為:'.$tmp[1].'之據點不存在';
      }

    }
    $service_point_img_pairs = ServicePointImgPair::where('service_point_id',$service_point->id)->get();
    foreach (  $service_point_img_pairs as  $service_point_img_pair ){
      $image_url = Image::where("id",$service_point_img_pair->image_id)->first()->image_url;
      $image_data = [];
      $image_data = explode("/",$image_url);
      $url = Storage::disk($image_data[0])->delete($image_data[1]);
      Image::where("id",$service_point_img_pair->image_id)->delete();
    }
    ServicePointImgPair::where('service_point_id',$service_point->id)->delete();

    Redis::del(md5($user->id.$group->id));

    Redis::hmset(md5($user->id.$group->id),'timestamp',strtotime('now'),'classname',__CLASS__,'service_point_id',$service_point->id,'service_point_name',$service_point_name);
    // $service_point_img_pairs = ServicePointImgPair::where('service_point_id',$is_server_exist->id)->get();
    // foreach ( $service_point_img_pairs as $service_point_img_pair ){
    //   $image_url = Image::where("id",$service_point_img_pair->image_id)->first()->image_url;
    //   Image::where('id',$service_point_img_pair->image_id)->delete();
    //   ServicePointImgPair::where('id',$service_point_img_pair->id)->delete();
    //   $image_data = [];
    //   $image_data = explode("/",$image_url);
    //   $url = Storage::disk($image_data[0])->delete($image_data[1]);
    // }
    $message = $this->command_data['reply_questions'][0];
    $message = trim($message,"\n");
    $continue_message = "\n".'或輸入 #{數字} 執行功能以繼續'."\n".'#1顯示#2清空#3完成';

    return $message.$continue_message;
  }
  protected function SessionFunction( $args=null ){

    $group = $args->group;
    if( !$group || $group->enble=='N' ){
      $message = "未授權";
      return $message;
    }
    $command = $args->command;
    $continue_message = "\n".'請輸入 #{數字} 執行功能以繼續'."\n".'#1顯示#2清空#3完成';
    $user = $args->user;
    $redis_con = Redis::connection();
    $redis = (object)$redis_con->hgetall(md5($user->id.$group->id));

    //Line傳來的圖通常帶純數id，只是小防一下不合法上傳
    if ( $args->type == 'image' ){
       if ( !is_numeric($command) ){
          return '圖片上傳有誤，請上傳據點圖片';
       }
    }
    else{
      if ($command=='#1'){
        $service_point_img_pairs = ServicePointImgPair::where('service_point_id',$redis->service_point_id)->offset(0)->limit(5)->get();
        $messages = [];
        foreach (  $service_point_img_pairs as  $service_point_img_pair ){
          $object = (object)[];
          $object->type = 'image';
          $image_url = Image::where("id",$service_point_img_pair->image_id)->first()->image_url;
          $image_data = [];
          $image_data = explode("/",$image_url);
          $url = Storage::disk($image_data[0])->url($image_data[1]);
          $object->originalContentUrl = $url;
          $object->previewImageUrl = $url;
          $messages[] = $object;
        }
        if (count($messages)==0){
          $messages = '目前無照片，請上傳圖片'."\n".'或輸入 #{數字} 執行功能以繼續'."\n".'#1顯示#2清空#3完成';
        }
        return $messages;
      }
      else if($command=='#2'){
        $service_point_img_pairs = ServicePointImgPair::where('service_point_id',$redis->service_point_id)->get();
        foreach (  $service_point_img_pairs as  $service_point_img_pair ){
          $image_url = Image::where("id",$service_point_img_pair->image_id)->first()->image_url;
          $image_data = [];
          $image_data = explode("/",$image_url);
          $url = Storage::disk($image_data[0])->delete($image_data[1]);
          Image::where("id",$service_point_img_pair->image_id)->delete();
        }
        ServicePointImgPair::where('service_point_id',$redis->service_point_id)->delete();
        $redis_con->hset(md5($user->id.$group->id),'redis_lock',false);
        return '該據點圖片已全部刪除';
      } 
      else if($command=='#3'){
        $redis_con->del(md5($user->id.$group->id));
        return '對話已關閉';
      }
      else{
        return '您的對話尚未結束，請上傳據點圖片'.$continue_message;
      }

    }
    while ( $redis_con->hget(md5($user->id.$group->id),'redis_lock') && ($redis_con->hget(md5($user->id.$group->id),'lock_time') > (time()-10) ) ){
      usleep(100000);
    }

    CommandUtil::lockRedis(md5($user->id.$group->id));
    // $redis_service_point_img_count = $redis_con->hget(md5($user->id.$group->id),'service_point_img_count');
    $service_point_img_count = ServicePointImgPair::where('service_point_id',$redis->service_point_id)->count();
    if ( $service_point_img_count > 4 ){
      CommandUtil::unlockRedis(md5($user->id.$group->id));
      return '該約客照片已滿五張，無法繼續新增'.$continue_message;
    }

    $result =  $this->setImg($command,$redis->service_point_id);
    if (!$result->result){
      return $result->data;
    }
    $images = new Image;
    $images->image_url=$result->disk_name.'/'.$result->data;
    $image_save_result = $images->save();
    if ( $image_save_result ){
      $service_point_images = new ServicePointImgPair ;
      $service_point_images->service_point_id = $redis->service_point_id;
      $service_point_images->image_id = $images->id;
      $service_point_images_pair_result = $service_point_images->save();
      if ( $service_point_images_pair_result ){
        CommandUtil::unlockRedis(md5($user->id.$group->id));
        if ( isset($args->is_final_event) && $args->is_final_event==true ){
          return '據點'.$redis->service_point_name.'圖片新增成功'.$continue_message;
        }
        else{
          return '據點'.$redis->service_point_name.'圖片新增成功'.$continue_message;
        }
      }
      else{
        CommandUtil::unlockRedis(md5($user->id.$group->id));
      return '據點圖片綁定配對失敗';
      }
    }
    else{
      CommandUtil::unlockRedis(md5($user->id.$group->id));
      return '據點圖片新增置資料庫失敗';
    }

  }

  private function setImg($photo_id,$service_point_id){
    $Result = [
      'result'=>false,
      'data'=>'',
    ];
    $helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
    $image_name = 'p'.$service_point_id.'_'.microtime(true).'.jpg';
    Storage::disk('Service_Point_images')->put($image_name, $helper->getContent($photo_id));
    $photo_exist = Storage::disk('Service_Point_images')->exists($image_name);

    if ( $photo_exist ){
      $Result['result']=true;
      $Result['data']=$image_name;
      $Result['disk_name']='Service_Point_images';
    }
    else{
      $Result['data']='圖片上傳失敗，請嘗試重新上傳，或聯繫系統工程師';
    }
    return (object)$Result;
  }

}
