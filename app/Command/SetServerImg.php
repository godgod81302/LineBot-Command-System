<?php
namespace App\Command;

use App\Model\Server;
use App\Line\ApiHelper;
use App\Model\Image;
use App\Model\ServerImgPair;
use Illuminate\Support\Facades\Redis;
use Illuminate\Cache\RedisLock;
use Illuminate\Support\Facades\Storage;
class SetServerImg extends BaseCommand{
  
  private static $instance;
  
  public static function getInstance(){
    if( !self::$instance ){
      self::$instance = new SetServerImg();
    }
    return self::$instance;
  }
  
  private function __construct(){
    $this->command_data = [
      'pre_command' => '&',
      'name' => '設服務員照片',
      'cmd' => '設照片',
      'description' => '設定服務員照片',
      'session_functions' => [
        'setImg',
      ],
      'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
      'reply_questions' => ['請上傳服務員照片'],
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
      $this->command_data['pre_command'].$this->command_data['cmd']."{服務員名}";
      
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

    $is_server_exist = Server::where('name',$command)->whereIn('partner_id',$partner_id_array)->first();
    if ( !$is_server_exist ){
      return '查無目標服務員，請再次確認(暫存服務員尚無法上傳圖片)';
    }
    
    Redis::del(md5($user->id.$group->id));

    // $server_img_count = ServerImgPair::where('server_id',$is_server_exist->id)->count();
    Redis::hmset(md5($user->id.$group->id),'timestamp',strtotime('now'),'classname',__CLASS__,'server_id',$is_server_exist->id,'server_name',$is_server_exist->name);
    // $server_img_pairs = ServerImgPair::where('server_id',$is_server_exist->id)->get();
    // foreach ( $server_img_pairs as $server_img_pair ){
    //   $image_url = Image::where("id",$server_img_pair->image_id)->first()->image_url;
    //   Image::where('id',$server_img_pair->image_id)->delete();
    //   ServerImgPair::where('id',$server_img_pair->id)->delete();
    //   $image_data = [];
    //   $image_data = explode("/",$image_url);
    //   $url = Storage::disk($image_data[0])->delete($image_data[1]);
    // }
    $message = $this->command_data['reply_questions'][0];
    $message = trim($message,"\n");
    $continue_message = "\n".'或輸入 #{數字} 執行功能以繼續'."\n".'#1 -> 顯示當前服務員已上傳照片'."\n".'#2 -> 重設服務員所有照片'."\n".'#3 -> 結束上傳對話';

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
          return '圖片上傳有誤，請上傳服務員圖片';
       }
    }
    else{
      if ($command=='#1'){
        $server_img_pairs = ServerImgPair::where('server_id',$redis->server_id)->offset(0)->limit(5)->get();
        $messages = [];
        foreach (  $server_img_pairs as  $server_img_pair ){
          $object = (object)[];
          $object->type = 'image';
          $image_url = Image::where("id",$server_img_pair->image_id)->first()->image_url;
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
        $server_img_pairs = ServerImgPair::where('server_id',$redis->server_id)->get();
        foreach (  $server_img_pairs as  $server_img_pair ){
          $image_url = Image::where("id",$server_img_pair->image_id)->first()->image_url;
          $image_data = [];
          $image_data = explode("/",$image_url);
          $url = Storage::disk($image_data[0])->delete($image_data[1]);
          Image::where("id",$server_img_pair->image_id)->delete();
        }
        ServerImgPair::where('server_id',$redis->server_id)->delete();
        $redis_con->hset(md5($user->id.$group->id),'redis_lock',false);
        return '該服務員圖片已全部刪除';
      } 
      else if($command=='#3'){
        $redis_con->del(md5($user->id.$group->id));
        return '對話已關閉';
      }
      else{
        return '您的對話尚未結束，請上傳服務員圖片'.$continue_message;
      }

    }
    while ( $redis_con->hget(md5($user->id.$group->id),'redis_lock') && ($redis_con->hget(md5($user->id.$group->id),'lock_time') > (time()-10) ) ){
      usleep(100000);
    }
    CommandUtil::lockRedis(md5($user->id.$group->id));
    // $redis_server_img_count = $redis_con->hget(md5($user->id.$group->id),'server_img_count');
    $server_img_count = ServerImgPair::where('server_id',$redis->server_id)->count();
    if ( $server_img_count > 4 ){
      CommandUtil::unlockRedis(md5($user->id.$group->id));
      return '該服務員照片已滿五張，無法繼續新增'.$continue_message;
    }

    $result =  $this->setImg($command,$redis->server_id);
    if (!$result->result){
      return $result->data;
    }
    $images = new Image;
    $images->image_url=$result->disk_name.'/'.$result->data;
    $image_save_result = $images->save();
    if ( $image_save_result ){
      $server_images = new ServerImgPair ;
      $server_images->server_id = $redis->server_id;
      $server_images->image_id = $images->id;
      $server_images_pair_result = $server_images->save();
      if ( $server_images_pair_result ){
        CommandUtil::unlockRedis(md5($user->id.$group->id));
        if ( isset($args->is_final_event) && $args->is_final_event==true ){
          return '服務員'.$redis->server_name.'圖片新增成功'.$continue_message;
        }
        else{
          return '服務員'.$redis->server_name.'圖片新增成功'.$continue_message;
        }
      }
      else{
        CommandUtil::unlockRedis(md5($user->id.$group->id));
      return '服務員圖片綁定配對失敗';
      }
    }
    else{
      CommandUtil::unlockRedis(md5($user->id.$group->id));
      return '服務員圖片新增置資料庫失敗';
    }

  }

  private function setImg($photo_id,$server_id){
    $Result = [
      'result'=>false,
      'data'=>'',
    ];
    $helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
    $image_name = 's'.$server_id.'_'.microtime(true).'.jpg';
    Storage::disk('Server_images')->put($image_name, $helper->getContent($photo_id));
    $photo_exist = Storage::disk('Server_images')->exists($image_name);

    if ( $photo_exist ){
      $Result['result']=true;
      $Result['data']=$image_name;
      $Result['disk_name']='Server_images';
    }
    else{
      $Result['data']='圖片上傳失敗，請嘗試重新上傳，或聯繫系統工程師';
    }
    return (object)$Result;
  }

}
