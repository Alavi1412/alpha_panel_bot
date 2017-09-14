<?php

/**
 * Created by PhpStorm.
 * User: alavi
 * Date: 9/14/17
 * Time: 5:01 PM
 */
class User
{
    private $user_id;       //user's telegram's unique ID
    private $username;      //user's telegram's username, May be NULL
    private $first_name;    //user's telegram's first name
    private $last_name;     //user's telegram's last name, May be NULL
    private $password;      //user's password saved in mysql, NULL for unknown
    private $level;         //user's level for interacting with bot
    private $helper_level;  //a place to save small levels
    private $text;          //user's content sent to telegram
    private $type;          //admin or customer
    private $temp;          //a place to save useful data while interacting
    private $update;        //whole object sent from telegram server to bot
    private $db;            //bot's database
    private $token;         //bot's token
    private $row;           //database row for current user

    public function __construct($user_id, $username, $first_name, $last_name, $text, $update, $token)
    {
        $this->user_id = $user_id;
        $this->username = $username;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->text = $text;
        $this->update = $update;
        $this->token = $token;
        $this->db = mysqli_connect("localhost", "root", "root", "alpha_panel_bot");
        mysqli_set_charset($this->db, "utf8mb4");
        $result = mysqli_query($this->db, "SELECT * FROM user WHERE user_id = {$this->user_id}");
        if ($row = mysqli_fetch_array($result))
        {
            $this->row = $row;
            $this->level = $row['level'];
            $this->helper_level = $row['helper_level'];
            $this->temp = $row['temp'];
            $this->password = $row['password'];
            $this->type = $row['type'];
        }
        else
        {
            $this->level = "not_found";
        }
    }

    private function sendMessage($text, $inline)
    {
        $result = json_decode($this->makeCurl("sendMessage", ["chat_id" => $this->user_id, "text" => $text, "reply_markup" => json_encode([
            "keyboard" =>
                $inline
        ])]));
        return $result;
    }

    private function setLevel($level)
    {
        return mysqli_query($this->db, "UPDATE user SET level = '{$level}' WHERE user_id = {$this->user_id}");
    }

    private function setHelperLevel($helperLevel)
    {
        return mysqli_query($this->db, "UPDATE user SET helper_level = '{$helperLevel}' WHERE user_id = {$this->user_id}");
    }

    private function setTemp($temp)
    {
        return mysqli_query($this->db, "UPDATE user SET temp = '{$temp}' WHERE user_id = {$this->user_id}");
    }

    private function setHelperLevelNull()
    {
        return mysqli_query($this->db, "UPDATE user SET helper_level = NULL WHERE user_id = {$this->user_id}");
    }

    private function setTempNull()
    {
        return mysqli_query($this->db, "UPDATE user SET temp = NULL WHERE user_id = {$this->user_id}");
    }

    public function process()
    {
        switch ($this->level)
        {
            case "not_found":
                $this->notFoundUser();
                break;
            case "ask_password":
                $this->passwordManager();
                break;
            case "main_menu_showed":
                $this->mainMenuManager();
                break;

        }
    }

    private function mainMenuManager()          //TODO HERE
    {
        if ($this->type == "admin")
        {
            switch ($this->text)
            {
                case "اضافه کردن ادمین":
                    $this->setHelperLevel("admin");
                    break;
                case "حذف کردن ادمین":
                    $this->setHelperLevel("admin");
                    break;
                case "اضافه کردن کاربر":
                    $this->setHelperLevel("customer");
                    break;
                case "حذف کردن کاربر":
                    $this->setHelperLevel("customer");
                    break;
                case "تبلیغات پایان یافته":
                    break;
                case "تبلیغات فعال":
                    break;
            }
        }
        elseif ($this->type == "customer")
        {

        }
    }

    private function userManager()
    {

    }

    private function showMainMenu()
    {
        $this->setLevel("main_menu_showed");
        $this->setTempNull();
        $this->setHelperLevelNull();
        if ($this->type == "admin")
            $this->sendMessage("انتخاب کنید.", [
                [
                    ["text" => "اضافه کردن ادمین"],["text" => "حذف کردن ادمین"]
                ],
                [
                    ["text" => "اضافه کردن کاربر"],["text" => "حذف کردن کاربر"]
                ],
                [
                    ["text" => "تبلیغات پایان یافته"],["text" => "تبلیغات فعال"]
                ]
            ]);
        elseif ($this->type == "customer")
            $this->sendMessage("انتخاب کنید.", [
                [
                    ["text" => "کمپین های اخیر"],["text" => "کمپین های فعال"]
                ],
                [
                    ["text" => "پشتیبانی"],["text" => "ساخت کمپین"]
                ]
            ]);
    }

    private function passwordManager()
    {
        if ($this->text == $this->password)
        {
            mysqli_query($this->db, "UPDATE user SET username = '{$this->username}', first_name = '{$this->first_name}', last_name = '{$this->last_name}' WHERE user_id = {$this->user_id}");
            $this->sendMessage("با موفقیت وارد شدید.", []);
            $this->showMainMenu();
        }
        else
            $this->sendMessage("رمز وارد شده نادرست است، لطفا با تیم پشتیبانی برای دریافت رمز تماس بگیرید.", []);
    }

    private function notFoundUser()
    {
        $this->sendMessage("آیدی شما:", []);
        $this->sendMessage($this->user_id, []);
        $this->sendMessage("شما مجاز به استفاده از این بات نیستید.", []);
    }

    private function makeCurl($method,$datas=[])    //make and receive requests to bot
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"https://api.telegram.org/bot{$this->token}/{$method}");    //TODO change to real token
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($datas));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec ($ch);
        curl_close ($ch);
        return $server_output;
    }

}