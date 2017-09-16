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
    private $adsDb;         //ads' database
    private $viewBot;       //connection with viewer bot
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
        $this->db = mysqli_connect("localhost", "root", "root", "alpha_panel_bot");             //TODO change to important info
        $this->adsDb = mysqli_connect("localhost", "root", "root", "autoreply");                //TODO change to important info
        $this->viewBot = mysqli_connect("localhost", "root", "root", "get_sticker_bot");
        mysqli_set_charset($this->db, "utf8mb4");
        mysqli_set_charset($this->adsDb, "utf8mb4");
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
            case "user_manager":
                $this->userManager("continue");
                break;
            case "active_ads":
                $this->activeAdsManager();
                break;
        }
    }

    private function mainMenuManager()
    {
        if ($this->type == "admin")
        {
            switch ($this->text)
            {
                case "اضافه کردن ادمین":
                    $this->setHelperLevel("admin");
                    $this->setTemp("add");
                    $this->userManager("start");
                    break;
                case "حذف کردن ادمین":
                    $this->setHelperLevel("admin");
                    $this->setTemp("remove");
                    $this->userManager("start");
                    break;
                case "اضافه کردن کاربر":
                    $this->setHelperLevel("customer");
                    $this->setTemp("add");
                    $this->userManager("start");
                    break;
                case "حذف کردن کاربر":
                    $this->setHelperLevel("customer");
                    $this->setTemp("remove");
                    $this->userManager("start");
                    break;
                case "تبلیغات پایان یافته":
                    $this->oldAdsManager();
                    break;
                case "تبلیغات فعال":
                    $this->activeAdsManager();
                    break;
            }
        }
        elseif ($this->type == "customer")
        {
            switch ($this->text)
            {
                case "کمپین های اخیر":
                    $this->oldAdsManager();
                    break;
                case "کمپین های فعال":
                    $this->activeAdsManager();
                    break;
                case "پشتیبانی":
                    $this->support();
                    break;
                case "ساخت کمپین":
                    $this->createCampaign();
                    break;

            }
        }
    }

    private function createCampaign()
    {
        $this->sendMessage("متن از مسعودی گرفته شود.", []);
        $this->showMainMenu();
    }

    private function support()
    {
        $this->sendMessage("متن از مسعودی گرفته شود.", []);
    }

    private function activeAdsManager()
    {
        $this->setLevel("active_ads");
        if ($this->type == "admin")
        {
            if ($this->helper_level == NULL)
            {
                $this->setHelperLevel("menu_asked");
                $result = mysqli_query($this->adsDb, "SELECT * FROM ads advertisement");
                if (@$row = mysqli_fetch_array($result))
                {
                    $ads = [ [ ["text" => $row['name'] ] ] ];
                    while ($row = mysqli_fetch_array($result))
                    {
                        array_push($ads, [ [ "text" => $row['name']  ] ]);
                    }
                    array_push($ads, [ [ "text" => "بازگشت"  ] ]);
                }else
                {
                    $this->sendMessage("تبلیغی یافت نشد.", []);
                    $this->showMainMenu();
                }

            }
            elseif ($this->helper_level == "menu_asked")
            {
                if ($this->text == "بازگشت")
                    $this->showMainMenu();
                else
                {
                    $result = mysqli_query($this->db, "SELECT * FROM advertisement WHERE name = '{$this->text}'");
                    if($row = mysqli_fetch_array($result))
                    {
                        $this->setTemp($row['name']);
                        $this->setHelperLevel("ads_menu");
                        $this->sendMessage("انتخاب کنید.", [
                            [
                                ["text" => "مشاهده ی تبلیغ"], ["text" => "اضافه کردن کاربر"], ["text" => "حذف کاربر"], ["text" => "بازگشت"]
                            ]
                        ]);
                    }
                    else
                    {
                        $this->sendMessage("تبلیغ مورد نظر یافت نشد.", []);
                        $this->showMainMenu();
                    }

                }
            }
            elseif ($this->helper_level == "ads_menu")
            {
                if ($this->text == "بازگشت")
                    $this->showMainMenu();
                elseif ($this->text == "مشاهده ی تبلیغ")
                {
                    $result = mysqli_query($this->viewBot, "SELECT * FROM ads WHERE ads_name = '{$this->temp}'");
                    if ($row = mysqli_fetch_array($result))
                    {
                        $total = $row['view'];
                        $view = [$row['view']];
                        $channel = [$row['owner']];
                        $text = "آیدی کانال:
                        {$channel}
                        تعداد بازدید:
                        {$view}";
                        while ($row = mysqli_fetch_array($result))
                        {
                            $text .= "آیدی کانال:
                        {$row['owner']}
                        تعداد بازدید:
                        {$row['view']}";
                        }
                        $this->sendMessage($text, []);
                        $this->showMainMenu();
                    }
                    else
                    {
                        $this->sendMessage("تبلیغ وارد شده یافت نشد.", []);
                        $this->showMainMenu();
                    }
                }
                elseif ($this->text == "حذف کاربر")
                {
                    $this->setHelperLevel("delete_user");
                    $this->sendMessage("یک پیام از شخص مورد نظر فوروارد کنید:", [
                        [
                            ["text" => "بازگشت"]
                        ]
                    ]);
                }
                elseif ($this->text == "اضافه کردن کاربر")
                {
                    $this->setHelperLevel("add_user");
                    $this->sendMessage("یک پیام از شخص مورد نظر فوروارد کنید:", [
                        [
                            ["text" => "بازگشت"]
                        ]
                    ]);
                }
            }
            elseif ($this->helper_level == "delete_user")
            {
                if ($this->text == "بازگشت")
                    $this->showMainMenu();
                else
                {
                    $user_id = $this->update->message->forward_from->id;
                    if(mysqli_query($this->db, "DELETE FROM active_ads WHERE viewer_id = {$user_id} AND ads_name = {$this->temp}"))
                    {
                        $this->sendMessage("با موفقیت پاک شد.", []);
                        $this->showMainMenu();
                    }
                    else
                    {
                        $this->sendMessage("انجام نشد.", []);
                        $this->showMainMenu();
                    }
                }
            }
            elseif ($this->helper_level == "add_user")
            {
                if ($this->text == "بازگشت")
                    $this->showMainMenu();
                else
                {
                    $user_id = $this->update->message->forward_from->id;
                    if(mysqli_query($this->db, "INSERT INTO active_ads (ads_name, viewer_id) VALUES ('{$this->temp}', {$user_id})"))
                    {
                        $this->sendMessage("با موفقیت اضافه شد.", []);
                        $this->showMainMenu();
                    }
                    else
                    {
                        $this->sendMessage("انجام نشد.", []);
                        $this->showMainMenu();
                    }
                }
            }
        }
        elseif ($this->type == "customer")
        {
            if ($this->helper_level == NULL)
            {
                $this->setHelperLevel("menu_asked");
                $result = mysqli_query($this->adsDb, "SELECT * FROM active_ads WHERE viewer_id = {$this->user_id}");
                if ($row = mysqli_fetch_array($result))
                {
                    $ads = [ [ ["text" => $row['ads_name'] ] ] ];
                    while ($row = mysqli_fetch_array($result))
                    {
                        array_push($ads, [ [ "text" => $row['ads_name']  ] ]);
                    }
                    array_push($ads, [ [ "text" => "بازگشت"  ] ]);
                }else
                {
                    $this->sendMessage("تبلیغی یافت نشد.", []);
                    $this->showMainMenu();
                }
            }
            elseif ($this->helper_level == "menu_asked")
            {
                $b = 0;
                if (mysqli_query("SELECT * FROM active_ads WHERE ads_name = '{$this->temp}' AND viewer_id = {$this->user_id}"))
                    $b = 1;
                $result = mysqli_query($this->viewBot, "SELECT * FROM ads WHERE ads_name = '{$this->temp}'");
                if ($row = mysqli_fetch_array($result) && $b == 1)
                {
                    $total = $row['view'];
                    $view = [$row['view']];
                    $channel = [$row['owner']];
                    $text = "آیدی کانال:
                        {$channel}
                        تعداد بازدید:
                        {$view}";
                    while ($row = mysqli_fetch_array($result))
                    {
                        $text .= "آیدی کانال:
                        {$row['owner']}
                        تعداد بازدید:
                        {$row['view']}";
                    }
                    $this->sendMessage($text, []);
                    $this->showMainMenu();
                }
                else
                {
                    $this->sendMessage("تبلیغ وارد شده یافت نشد.", []);
                    $this->showMainMenu();
                }
            }
        }
    }

    private function oldAdsManager()
    {
        if ($this->type == "admin")
        {
            if ($this->helper_level == NULL)
            {
                $this->setHelperLevel("ads_asked");
                $result = mysqli_query($this->db, "SELECT * FROM old_ads");
                if ($row = mysqli_fetch_array($result))
                {
                    $text = $row['ads_name'];
                    while ($row = mysqli_fetch_array($result))
                    {
                        $text .= "\n";
                        $text .= $row['ads_name'];
                    }
                    $this->sendMessage("اسم تبلیغ مورد نظر را وارد کنید.", []);
                    $this->sendMessage($text, [
                        [
                            ["text" => "بازگشت"]
                        ]
                    ]);
                }
                else
                {
                    $this->sendMessage("تبلیغی یافت نشد.", []);
                    $this->showMainMenu();
                }
            }
            elseif ($this->helper_level == "ads_asked")
            {
                $result = mysqli_query($this->db, "SELECT * FROM old_ads WHERE ads_name = '{$this->text}'");
                if ($row = mysqli_fetch_array($result))
                {
                    //TODO show ads
                }
                else
                {
                    $this->sendMessage("تبلیغ وارد شده اشتباه است.", []);
                    $this->showMainMenu();
                }
            }
        }
        elseif ($this->type == "customer")
        {
            if ($this->helper_level == NULL)
            {
                $this->setHelperLevel("ads_asked");
                $result = mysqli_query($this->db, "SELECT * FROM old_ads WHERE view_id = {$this->user_id}");
                if ($row = mysqli_fetch_array($result))
                {
                    $text = $row['ads_name'];
                    while ($row = mysqli_fetch_array($result))
                    {
                        $text .= "\n";
                        $text .= $row['ads_name'];
                    }
                    $this->sendMessage("اسم تبلیغ مورد نظر را وارد کنید.", []);
                    $this->sendMessage($text, [
                        [
                            ["text" => "بازگشت"]
                        ]
                    ]);
                }
                else
                {
                    $this->sendMessage("تبلیغی یافت نشد.", []);
                    $this->showMainMenu();
                }
            }
            elseif ($this->helper_level == "ads_asked")
            {
                $result = mysqli_query($this->db, "SELECT * FROM old_ads WHERE ads_name = '{$this->text}' AND view_id = {$this->user_id}");
                if ($row = mysqli_fetch_array($result))
                {
                    //TODO show ads
                }
                else
                {
                    $this->sendMessage("تبلیغ وارد شده اشتباه است.", []);
                    $this->showMainMenu();
                }
            }
        }
    }

    private function userManager($entry)
    {
        $this->setLevel("user_manager");
        if ($this->type == "admin")
        {
            if ($entry == "start")
                $this->sendMessage("یا پیام از شخص مورد نظر فوروارد کنید.", [
                    [
                        ["text" => "بازگشت"]
                    ]
                ]);
            elseif ($this->text == "بازگشت")
                $this->showMainMenu();
            else
            {
                $user_id = $this->update->message->forward_from->id;
                $this->userDatabaseManager($user_id);
            }
        }
    }

    private function userDatabaseManager($user_id)
    {
        if ($this->temp == "remove")
            $string = "DELETE FROM user WHERE user_id = {$user_id}";
        elseif ($this->temp == "add")
        {
            $password = rand(1000000, 9999999);
            $string = "INSERT INTO user (user_id, password,type) VALUES ({$user_id}, {$password}, {$this->helper_level})";
        }
        if(mysqli_query($this->db, $string))
            $this->sendMessage("با موفقیت انجام شد.", []);
        else
            $this->sendMessage("انجام نشد.", []);
        $this->showMainMenu();
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
        mysqli_query($this->db, "INSERT INTO unkown_user (user_id, first_name, last_name, username, text) VALUES ({$this->user_id}, '{$this->first_name}', '{$this->last_name}', '{$this->username}', '{$this->text}')");
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