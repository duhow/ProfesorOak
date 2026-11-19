<?php

class StrictAntispam {
    // Returns TRUE when processing must stop, even if Telegram rejects an action.
    public static function moderate($telegram, $pokemon){
        if(!$telegram->is_chat_group()){ return FALSE; }
        $update = $telegram->dump();
        $message = isset($update['edited_message']) ? $update['edited_message']
            : (isset($update['message']) ? $update['message'] : NULL);
        if(!$message || !isset($message['chat']['id'], $message['message_id'])){ return FALSE; }
        $chat = $message['chat']['id'];
        $mode = $pokemon->settings($chat, 'antispam');
        if($mode !== 2 && $mode !== '2'){ return FALSE; }
        if(!self::matches($message)){ return FALSE; }

        // Deletion and banning are independent: attempt both even on API failure.
        try {
            if($telegram->send->delete($message['message_id'], $chat) === FALSE){
                error_log('Strict antispam: message deletion failed');
            }
        } catch(Exception $e){
            error_log('Strict antispam: message deletion failed');
        }
        // Anonymous/channel posts have no bannable individual sender.
        if(!isset($message['sender_chat']) && !empty($message['from']['id'])){
            try {
                if($telegram->send->ban($message['from']['id'], $chat) === FALSE){
                    error_log('Strict antispam: user ban failed');
                }
            } catch(Exception $e){
                error_log('Strict antispam: user ban failed');
            }
        }
        return TRUE;
    }

    private static function matches($message){
        if(!empty($message['contact']['phone_number'])){ return TRUE; }
        foreach(array('entities', 'caption_entities') as $field){
            if(empty($message[$field])){ continue; }
            foreach($message[$field] as $entity){
                if(isset($entity['type']) && in_array($entity['type'],
                    array('url', 'text_link', 'mention', 'phone_number'), TRUE)){
                    return TRUE;
                }
            }
        }
        foreach(array('text', 'caption') as $field){
            if(empty($message[$field])){ continue; }
            $text = $message[$field];
            // Use entity types rather than slicing Telegram's UTF-16 offsets.
            if(preg_match('~(?<![\pL\pN_@])@[a-z][a-z0-9_]{0,31}\b~iu', $text)){
                return TRUE;
            }
            if(preg_match('~\b[a-z][a-z0-9+.-]*://[^\s<>]+|\b(?:mailto|tel|sms|magnet|data|geo|sip|tg):[^\s<>]+~iu', $text)){
                return TRUE;
            }
            // Bare domains, including internationalized domains and invite URLs.
            if(preg_match('~(?<![\pL\pN_@.-])(?:[\pL\pN](?:[\pL\pN-]*[\pL\pN])?\.)+(?:[\pL]{2,63}|xn--[a-z0-9-]+)(?![\pL\pN_-])~iu', $text)){
                return TRUE;
            }
            if(preg_match('~(?<![\w.])(?:\d{1,3}\.){3}\d{1,3}(?::\d+)?(?:/[^\s<>]*)?(?![\w.])~u', $text)){
                return TRUE;
            }
            // Do not mistake ordinary ISO calendar dates for phone numbers.
            $text = preg_replace('~(?<!\d)\d{4}-\d{2}-\d{2}(?!\d)~', ' ', $text);
            // Bound digits during matching so adjacent phone numbers do not
            // merge into one oversized candidate and evade detection.
            if(preg_match('~(?<![\pL\pN])\+?(?:\d[ ().-]*){6,14}\d(?![\pL\pN])~u', $text)){
                return TRUE;
            }
        }
        return FALSE;
    }
}
