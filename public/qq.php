<?php
require_once 'madeline.php';
require_once '/vendor/autoload.php';
class myProcess{
    private $app_id;
    private $api_hash;
    public function __construct($argv) {
        $client = new Predis\Client(array('host' => "127.0.0.1", "port" => 6379, array("prefix" => "php:")));
        $client->set("tg_listener_run",true);
        $this->app_id = 18333171;
        $this->api_hash = 'dc8e192668840830bb8ad66c1691fa52';
        $MadelineProto = new \danog\MadelineProto\API('session.madeline',['app_info' => ['api_id' => $this->app_id, 'api_hash' => $this->api_hash], 'updates' => ['handle_updates' => false]]);
        $MadelineProto->start();
        // $MadelineProto->async(false);
        // $id =  $this->getMessages($MadelineProto);
        do{
            $list_of_channels = $MadelineProto->getFullDialogs();

            foreach ($list_of_channels as $i => $peer) {
                if ( isset($peer['notify_settings']['mute_until']) ){
                    if ( $peer['notify_settings']['mute_until']>0 ){
                        continue;
                    }
                }
                // $this->get_pwr_chat($MadelineProto,);
                if (
                    $peer['peer']['_'] == 'peerUser' || 
                    ($peer['unread_count'] == 0 && $peer['unread_mark'] == "")
                ){
                    continue;
                }
                $peer_string = '';
                if ( isset($peer['peer']['user_id']) ){
                    $peer_string = 'user#'.$peer['peer']['user_id'];
                }
                if ( isset($peer['peer']['channel_id']) ){
                    $peer_string = 'channel#'.$peer['peer']['channel_id'];
                }
                if ( isset($peer['peer']['chat_id']) ){
                    $peer_string = 'chat#'.$peer['peer']['chat_id'];
                }
    
                $messages_Messages = $MadelineProto->messages->getHistory([
                                    'peer' => $peer_string, 
                                    'offset_id' => 0, 
                                    'offset_date' => 0,
                                    'add_offset' => 0, 
                                    'limit' =>  $peer['unread_count'], 
                                    'max_id' => 0,
                                    'min_id' => 0
                ]);
    
                if (count($messages_Messages['messages']) == 0){
                    continue;
                }
                else{
                    
                }
    
            }
        }while( $client->get("tg_listener_run") );

        // $content = $MadelineProto->get_full_info('@apexwu0817');
        // $me = $MadelineProto->getSelf();
        // file_put_contents("chat_log.json",json_encode($id));

    }
    //抓取所有群組資料
    private function getAllChats($MadelineProto){
        return $MadelineProto->messages->getAllChats()['chats'];
    }
    //根据群组信息获取群组所有用户信息（$groupInfo可以是邀请链接或id，例如’https://t.me/danogentili‘/’chat#492772765’/’channel#38575794’）
    private function get_pwr_chat($MadelineProto,$group_info){
        return $MadelineProto->get_pwr_chat($group_info);
    }
    //根据username获取用户详细信息
    private function get_full_info($MadelineProto,$username){
        return $MadelineProto->get_full_info($username);
    }
    //发送消息（文中 username为获取的用户username，传入时前面加前缀@，如@test， u s e r n a m e 为 获 取 的 用 户 u s e r n a m e ， 传 入 时 前 面 加 前 缀 @ ， 如 @ t e s t ， message则直接为想要发送的消息）
    private function sendMessage($MadelineProto,$peer,$message){
        return $MadelineProto->messages->sendMessage(['peer' => $peer, 'message' => $message]);
    }
    //getArmyMessage
    private function getMessages($MadelineProto){
        return $MadelineProto->messages->getMessages(['id' => [478156358], ]);
    }
    
}

$process = new myProcess($argv);
