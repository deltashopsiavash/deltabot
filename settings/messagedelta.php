<?php 
include_once '../baseInfo.php';
include_once '../config.php';
include_once 'jdf.php';

$rateLimit = $botState['rateLimit']??0;
if(time() > $rateLimit){
    $rate = json_decode(curl_get_file_contents("https://api.pooleno.ir/v1/currency/short-name/trx?type=buy"),true);
    $botState['USDRate'] = round($rate['priceUsdt'],2);
    $botState['TRXRate'] = round($rate['priceFiat'] / 10,2);
    $botState['rateLimit'] = strtotime("+1 hour");
    
    $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'BOT_STATES'");
    $stmt->execute();
    $isExists = $stmt->get_result();
    $stmt->close();
    if($isExists->num_rows>0) $query = "UPDATE `setting` SET `value` = ? WHERE `type` = 'BOT_STATES'";
    else $query = "INSERT INTO `setting` (`type`, `value`) VALUES ('BOT_STATES', ?)";
    $newData = json_encode($botState);
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $newData);
    $stmt->execute();
    $stmt->close();
}

$stmt = $connection->prepare("SELECT * FROM `send_list` WHERE `state` = 1 LIMIT 1");
$stmt->execute();
$list = $stmt->get_result();
$stmt->close();

if($list->num_rows > 0){
    $info = $list->fetch_assoc();
    
    $sendId = $info['id'];
    $offset = $info['offset'];
    $type = $info['type'];
    $pinState = false;
    if(function_exists('str_ends_with') && str_ends_with($type, '_pin')){
        $pinState = true;
        $type = str_replace('_pin','',$type);
    }

    $file_id = $info['file_id'];
    $chat_id = $info['chat_id'];
    $text = $info['text'];
    $message_id = $info['message_id'];
    
    if($offset == '0'){
        if($type == "forwardall") $msg = "عملیات هدایت همگانی شروع شد";
        else $msg = "عملیات ارسال پیام همگانی شروع شد";
        
        bot('sendMessage',[
            'chat_id'=>$admin,
            'text'=>$msg
            ]);
    }
    
    $stmt = $connection->prepare("SELECT * FROM `users`ORDER BY `id` LIMIT 50 OFFSET ?");
    $stmt->bind_param("i", $offset);
    $stmt->execute();
    $usersList = $stmt->get_result();
    $stmt->close();
    
    $keys = json_encode([
                'inline_keyboard' => [
                    [['text'=>$buttonValues['start_bot'],'callback_data'=>"mainMenu"]]
                    ]
            ]);
    if($usersList->num_rows > 0) {
        while($user = $usersList->fetch_assoc()){
            if($type == 'text'){
                $r = sendMessage($text,$keys,null,$user['userid']);
                if($pinState && isset($r->result->message_id)){
                    bot('pinChatMessage',['chat_id'=>$user['userid'],'message_id'=>$r->result->message_id,'disable_notification'=>true]);
                }
            }elseif($type == 'music'){
                $r = bot('sendAudio',[
                    'chat_id' => $user['userid'],
                    'audio' => $file_id,
                    'caption' => $text,
                    'reply_markup'=>$keys
                ]);
                if($pinState && isset($r->result->message_id)){
                    bot('pinChatMessage',['chat_id'=>$user['userid'],'message_id'=>$r->result->message_id,'disable_notification'=>true]);
                }
            }elseif($type == 'video'){
                $r = bot('sendVideo',[
                    'chat_id' => $user['userid'],
                    'video' => $file_id,
                    'caption' => $text,
                    'reply_markup'=>$keys
                ]);
                if($pinState && isset($r->result->message_id)){
                    bot('pinChatMessage',['chat_id'=>$user['userid'],'message_id'=>$r->result->message_id,'disable_notification'=>true]);
                }
            }elseif($type == 'voice'){
                $r = bot('sendVoice',[
                    'chat_id' => $user['userid'],
                    'voice' => $file_id,
                    'caption' => $text,
                    'reply_markup'=>$keys
                ]);
                if($pinState && isset($r->result->message_id)){
                    bot('pinChatMessage',['chat_id'=>$user['userid'],'message_id'=>$r->result->message_id,'disable_notification'=>true]);
                }
            }elseif($type == 'document'){
                $r = bot('sendDocument',[
                    'chat_id' => $user['userid'],
                    'document' => $file_id,
                    'caption' => $text,
                    'reply_markup'=>$keys
                ]);
                if($pinState && isset($r->result->message_id)){
                    bot('pinChatMessage',['chat_id'=>$user['userid'],'message_id'=>$r->result->message_id,'disable_notification'=>true]);
                }
            }elseif($type == 'photo'){
                bot('sendPhoto', [
                    'chat_id' => $user['userid'],
                    'photo' => $file_id,
                    'caption' => $text,
                    'reply_markup'=>$keys
                ]); 
            }elseif($type == "forwardall"){
                forwardmessage($user['userid'], $chat_id, $message_id);
            }
            else {
                $r = bot('sendDocument',[
                    'chat_id' => $user['userid'],
                    'document' => $file_id,
                    'caption' => $text,
                    'reply_markup'=>$keys
                ]);
                if($pinState && isset($r->result->message_id)){
                    bot('pinChatMessage',['chat_id'=>$user['userid'],'message_id'=>$r->result->message_id,'disable_notification'=>true]);
                }
            }
            $offset++;
        }
        $stmt = $connection->prepare("UPDATE `send_list` SET `offset` = ? WHERE `id` = ?");
        $stmt->bind_param("ii", $offset, $sendId);
        $stmt->execute();
        $stmt->close();
    }else{
        if($type == "forwardall") $msg = "عملیات هدایت همگانی با موفقیت انجام شد";
        else $msg = "عملیات ارسال پیام همگانی با موفقیت انجام شد";
        
        bot('sendMessage',[
            'chat_id'=>$admin,
            'text'=>$msg . "\nبه " . $offset . " نفر پیامتو فرستادم"
            ]);
            
        $stmt = $connection->prepare("DELETE FROM `send_list` WHERE `id` = ?");
        $stmt->bind_param('i', $sendId);
        $stmt->execute();
        $stmt->close();
    }
}


?>
