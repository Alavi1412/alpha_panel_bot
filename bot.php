<?php

/**
 * Created by PhpStorm.
 * User: alavi
 * Date: 9/14/17
 * Time: 4:56 PM
 */
include 'User.php';

function makeCurl($method,$datas=[])    //make and receive requests to bot
{
    global $token;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($datas));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec ($ch);
    curl_close ($ch);
    return $server_output;
}
$token =  "409524028:AAH3UxJW6_QusuiQbc_OWR-hoR0Ztfj6Zc0";      //TODO change to real token
$last_updated_id = 0;           //should be removed

function main()
{
    global $last_updated_id;
    global $token;
//    $update = json_decode(file_get_contents("php://input"));          //should not be comment
    $updates = json_decode(makeCurl("getUpdates",["offset"=>($last_updated_id+1)]));        //should be removed
    if($updates->ok == true && count($updates->result) > 0) {               //should be removed
        foreach ($updates->result as $update) {                             //should be removed
            if (@$update->callback_query) {
                makeCurl("answerCallbackQuery", ["callback_query_id" => $update->callback_query->id]);
                $text = $update->callback_query->data;
                $user_id = $update->callback_query->from->id;
                $first_name = $update->callback_query->from->first_name;
                $last_name = $update->callback_query->from->last_name;
                $username = $update->callback_query->from->username;
            } else {
                $text = $update->message->text;
                $user_id = $update->message->chat->id;
                $username = $update->message->from->username;
                $first_name = $update->message->from->first_name;
                $last_name = $update->message->from->last_name;
            }
            $User = new User($user_id, $username, $first_name, $last_name, $text, $update, $token);
            $User->process();
            $last_updated_id = $update->update_id;              //should be removed
        }           //should be removed
    }               //should be removed
}
while(1)            //should be removed
{
    main();
}