<?php
ini_set('date.timezone', 'Asia/Jakarta');

// include "inc-ip.php";
include "sanitize.inc.php";
include "inc-func.php";

$uniqode = uniqid() . " - " . date("Y-m-d H:i:s");

//todo:
//data hanya ada pada satu tempat saja (tidak doble), akses pakai api
$json = file_get_contents('php://input');
// echo $json."<BR>";

// $json = '{"accessToken":"b125de21-90f5-457b-913e-fdfda40b17df","ticketid":"hdtb0f5wvw6xzoks1734a06g","botid":"b0f5wvw6xzo","ticketstatus":"solved","action":"helpdeskticketupdate"}';
// $json = '{"accessToken":"b125de21-90f5-457b-913e-fdfda40b17df","botid":"b0f5wvw6xzo","userid":"6287788874666","wait":false,"group":"true","action":"helpdeskticketcreate"}';
// $json = '{
//   "accessToken": "b125de21-90f5-457b-913e-fdfda40b17df",
//   "action": "helpdeskticketassign",
//   "botid": "b0f5wvw6xzo"
// }';

// $json = '{
//     "accessToken": "b125de21-90f5-457b-913e-fdfda40b17df",
//     "action": "helpdeskticketassign",
//     "botid": "btmnz61o1x8"
// }';

$json = json_decode($json, true);

$rndStr = md5(round(microtime(true) * 1000));
$rndStr = substr($rndStr, 0, 10);

$startTime = strtotime("now");

// exit;
if (in_array($json["accessToken"], ['863c8e04-9d16-45dc-8055-0805d5333014'])) {

    //---------------------------
    //per 23 april 2021 ditambah lock supaya tidak double assign
    $botId = sanitize_paranoid_string($json["botid"]);
    // checkLock($botId);
    // writeLock($botId);
    //---------------------------

    function randString()
    {
        $length = 10;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $randomString;
    }

    // echo "1wewwwwwww<BR>";
    if ($json["action"] == "test") {

        echo '{"result":"success"}';

    } else if ($json["action"] == "helpdeskticketcreate") {

        require_once "inc-db.php";

        writeLog("[$uniqode - helpdeskticketcreate] Receiving request " . json_encode($json) . " at : " . date("Y-m-d H:i:s"), "logDebug_" . date("Y-m-d_H"));

        $botId = sanitize_paranoid_string($json["botid"]);
        $userId = sanitize_paranoid_string($json["userid"]);

        if (is_bool($json["wait"])) {
            $waitOk = $json["wait"];
        } else {
            $waitOk = strtolower(sanitize_paranoid_string($json["wait"]));
            if (($waitOk == "true") || ($waitOk === "1")) {
                $waitOk = true;
            } else {
                $waitOk = false;
            } //if
        }

        if (is_bool($json["group"])) {
            $groupBySubject = $json["group"];
        } else {
            $groupBySubject = strtolower(sanitize_paranoid_string($json["group"]));
            if (($groupBySubject == "true") || ($groupBySubject === "1")) {
                $groupBySubject = true;
            } else {
                $groupBySubject = false;
            } //if
        }

        $subject = sanitize_paranoid_string($json["subject"]);
        $ticketGroupVal = sanitize_paranoid_string($json["subject"]);
        //$category = sanitize_paranoid_string($json["category"]);
        $ticketCategory = sanitize_paranoid_string($json["category"]);

        //per 27 agustus 2021
        //-------------------------------------------
        $accountId = "";

        //if(isset($json["accountid"])) {
        //    $accountId = sanitize_paranoid_string($json["accountid"]);
        //} //if

        if (isset($json["account"])) {
            $account = sanitize_paranoid_string($json["account"]);

            //ambil accountId
            $query = 'SELECT botika_accounts.account_id FROM botika_bot_access ' .
                'LEFT JOIN botika_accounts ON botika_accounts.account_id = botika_bot_access.account_id ' .
                'WHERE (' .
                'botika_accounts.account_id = "' . $account . '" OR ' .
                'email = "' . $account . '" OR ' .
                'username = "' . $account . '"' .
                ') AND bot_id = "' . $botId . '" ';
            $result = mysqli_query($mysqli, $query);
            $db_field = mysqli_fetch_assoc($result);

            $accountId = $db_field["account_id"];
        } //if
        //-------------------------------------------
        //per 7 oktober 2020, bisa pilih per departemen. misal support dan sales
        $departmentId = "";
        $departmentName = "";
        $workspaceId = null;

        //per 27 agustus 2021

        //if(isset($json["departmentid"])){ $departmentId = sanitize_paranoid_string($json["departmentid"]); }
        //if(isset($json["departmentname"])){ $departmentName = sanitize_paranoid_string($json["departmentname"]); }

        //if(
        //    ($departmentId!="") ||
        //    ($departmentName!="")
        //) {

        if (isset($json["department"])) {
            $department = sanitize_paranoid_string($json["department"]);

            //cari workspace bila ada set berdasar department
            // $query = "SELECT workspace_id FROM botika_omny_workspaces ".
            //     "LEFT JOIN botika_bots ON ".
            //     "botika_omny_workspaces.account_id = botika_bots.account_id ".
            //     "WHERE bot_id = '".$botId."' ";
            $query = "SELECT workspace_id FROM botika_omny_workspace_bots " .
                "WHERE bot_id = '" . $botId . "' ";
            $result = mysqli_query($mysqli, $query);
            $db_field = mysqli_fetch_assoc($result);
            $workspaceId = $db_field["workspace_id"];
            //} //if

            //if(($departmentName!="") && ($departmentId=="")) {

            $query = "SELECT departement_id FROM botika_omny_workspace_departements " .
                "WHERE (" .
                "departement_id = '" . $department . "' or " .
                "departement_name = '" . $department . "'" .
                ")" .
                "AND workspace_id = '" . $workspaceId . "'";

            $result = mysqli_query($mysqli, $query);
            $db_field = mysqli_fetch_assoc($result);

            $departmentId = $db_field["departement_id"];

        } //if

        //---------------------------------------------------------------------
        if (isset($json["chatid"])) {
            //echo "here";
            $chatId = sanitize_paranoid_string($json["chatid"]);

            sleep(2);
            //cari chat type, message
            $query = "SELECT chat_id, chat_type, message, creation_date FROM botika_chat_logs WHERE " .
                "chat_id = '" . $chatId . "' AND user_id = '" . $userId . "' ";

            //per 25 oktober 2022, tambah paramteter apabila follow up dari agent/admin agar tidak dianggap tidak valid
            if (!isset($json["followup"]) || !$json["followup"]) {
                $query .= "and message_from = 'User' ";
            }
            //"order by creation_date desc ".
            $query .= "order by chat_log_idx desc " .
                "limit 0,1";

            $result = mysqli_query($mysqli, $query);

            if (mysqli_num_rows($result) == 0) {

                // chat id tidak valid
                $chatId = "";

            } else {
                $db_field = mysqli_fetch_assoc($result);

                $chatId = $db_field["chat_id"];
                $chatType = $db_field["chat_type"];
                $chatMessage = $db_field["message"];
                $chatCreationDate = $db_field["creation_date"];

            } // if
        } //if

        if ($chatId == "") {

            $checkStartTime = strtotime("now");

            // cari chat id dan menanggulangi chat belum masuk ke database
            $try = 0;
            $db_field = [];
            $timeMin = strtotime('-30 minutes');
            while ($try < 3 && empty($db_field)) {
                sleep(2);
                //cari chat id saat ini
                $query = "SELECT chat_id, chat_type, message, creation_date FROM botika_chat_logs WHERE " .
                "user_id = '" . $userId . "' and message_from = 'User' and bot_id = '" . $botId . "' " .
                //"order by creation_date desc ".
                "order by chat_log_idx desc " .
                    "limit 0,1";

                $result = mysqli_query($mysqli, $query);
                $try++;

                if (mysqli_num_rows($result) == 0) {
                    continue;
                }
                $res = mysqli_fetch_assoc($result);
                $chatTime = strtotime($res["creation_date"]);
                if ($chatTime < $timeMin && ($res["chat_type"] == 'EMAIL' || $res["chat_type"] == 'EMAILOUTLOOK')) {
                    continue;
                }
                writeLog("[$uniqode - helpdeskticketcreate] Getting chatId, with result : " . json_encode($res) . ", on try : $try", "logDebug_" . date("Y-m-d_H"));
                $db_field = $res;
            }
            if (!empty($db_field)) {

                $chatId = $db_field["chat_id"];
                $chatType = $db_field["chat_type"];
                $chatMessage = $db_field["message"];
                $chatCreationDate = $db_field["creation_date"];

            } // if

            $checkEndTime = strtotime("now");
            $checkDiffTime = $checkEndTime - $checkStartTime;

            if (abs($checkDiffTime) > 5) {
                writeLog("[$uniqode - helpdeskticketcreate] " . $query . " diffTime:" . $checkDiffTime, "logSlow_" . date("Y-n-j_H"));
            } //if

        } //if

        //check apakah ada dari admin, case admin duluan yg follow up
        if ($chatId == "") {

            $checkStartTime = strtotime("now");

            //cari chat id saat ini
            $query = "SELECT chat_id, chat_type, message, creation_date FROM botika_chat_logs WHERE " .
            "user_id = '" . $userId . "' and message_from = 'Admin' and bot_id = '" . $botId . "' " .
            //"order by creation_date desc ".
            "order by chat_log_idx desc " .
                "limit 0,1";

            $result = mysqli_query($mysqli, $query);

            if (mysqli_num_rows($result) == 0) {

                // chat id tidak valid
                $chatId = "";

            } else {
                $db_field = mysqli_fetch_assoc($result);

                $chatId = $db_field["chat_id"];
                $chatType = $db_field["chat_type"];
                $chatMessage = $db_field["message"];
                $chatCreationDate = $db_field["creation_date"];

            } // if

            $checkEndTime = strtotime("now");
            $checkDiffTime = $checkEndTime - $checkStartTime;

            if (abs($checkDiffTime) > 5) {
                writeLog("[$uniqode - helpdeskticketcreate] " . $query . " diffTime:" . $checkDiffTime, "logSlow_" . date("Y-n-j_H"));
            } //if

        } //if
        // echo $query."<BR>";
        //writeLog($query, "logHelpdeskTicket");

        //echo $chatId;
        //echo $departmentId;
        //exit;
        // echo "check1<BR>";
        //---------------------------------------------------------------------
        //     socket data      //
        $querySetup = 'select agent_chat_access from botika_bot_settings where bot_id = "' . $botId . '"';
        $resultSetup = mysqli_query($mysqli, $querySetup);
        $fetchSetup = $resultSetup->fetch_all(MYSQLI_ASSOC);
        $chatAccess = $fetchSetup[0]['agent_chat_access'];

        if (isset($accountId) && !empty($accountId)) {
            //agent
            $queryAgent = 'select ba.account_id, ba.username from botika_accounts ba where ba.account_id  = "' . $accountId . '" ';
            $resultAgent = mysqli_query($mysqli, $queryAgent);
            $agentData = $resultAgent->fetch_all(MYSQLI_ASSOC);

            $agent = $agentData[0]['account_id'];
            $agentName = $agentData[0]['username'];
        } else {
            $agent = "";
            $agentName = "";
        } //if

        // writeLog($queryAcc, "logHelpdeskTicket");

        //user
        $queryUser = 'select user_id userId, concat(first_name,\' \', last_name) name, profile_pic profPic from botika_users where user_id = "' . $userId . '"';
        $resultUser = mysqli_query($mysqli, $queryUser);
        $user = $resultUser->fetch_all(MYSQLI_ASSOC);

        //---------------------------------------------------------------------
        if ($chatId != "") {

            // }

            //cari apakah user ini pernah punya ticket
            //cari chat log id start untuk chat id ini
            $query = "SELECT ticket_id, ticket_idx, ticket_status, chat_log_id_start, chat_log_idx_start, chat_log_id_end, ticket_assignee_id, creation_date FROM botika_helpdesk_tickets WHERE " .
                "bot_id = '" . $botId . "' AND user_id = '" . $userId . "' ";

            // $subject = "";

            $bypassSubject = false;
            $subject_select = "";
            if ($chatType == "EMAIL" || $chatType == "EMAILOUTLOOK") {
                //khusus email, ticket dibedakan dari subject
                //{"msg":[{"text":"<meta http-equiv=\"Content-Type\" content=\"text\/html; charset=utf-8\"><div dir=\"ltr\">testing<\/div> <br>"}],"email":"Vads.sulistioa@xl.co.id","subject":"test1","replyto":"nura1068@gmail.com","cc":"","bcc":""}
                $emailJson = json_decode($chatMessage);

                # 8 okt 24, cocokkan subject dengan yng dari chatlog, jika berbeda gunakan dari param
                if(mb_detect_encoding($json['subject']) == 'ASCII') $sub = mb_decode_mimeheader($json['subject']);
                else $sub = $json['subject'];

                # ahmadnurbrasta@gmail.com
                // if ($emailJson->email == 'ahmadnurbrasta@gmail.com') {
                    # change emoticon to unicode
                    $sub = substr(json_encode($json['subject']), 1, -1);
                    $subject = $sub;
                    $subject_select = addslashes($subject);
                    writeLog("[SALMA DEBUG $botId SUBJECT 1 TEST] $json[subject] : $sub : $subject : $subject_select");
                // } else {
                //     $subject = json_encode($sub, JSON_UNESCAPED_SLASHES);
                //     $subject = str_replace('\"', '"', $subject);
                // }
                # remove only the first and last double quotes
                if (strlen($subject) > 0 && substr($subject, 0, 1) === '"' && substr($subject, -1) === '"') {
                    $subject = substr($subject, 1, -1);
                }
                
                // if ($emailJson->email != 'ahmadnurbrasta@gmail.com') {
                //     if(strstr($subject, $emailJson->subject)) {
                //         writeLog("[SALMA DEBUG $botId SUBJECT 2] $subject : $emailJson->subject");
                //         $subject = $emailJson->subject;
                //     }
                // }

                //bersihkan subject
                $clean = false;

                while (!$clean) {

                    $found = false;

                    //re:
                    $testStr = strtolower(substr($subject, 0, 3));
                    $select_testStr = strtolower(substr($subject_select, 0, 3));

                    writeLog("[SALMA DEBUG $botId RESUBJECT 3] $subject : $subject_select");

                    if ($select_testStr == "re:" || $testStr == "re:") {
                        $subject = trim(substr($subject, strlen("re:")));
                        $subject_select = trim(substr($subject_select, strlen("re:")));
                        $found = true;
                    } //if

                    //fwd:
                    $testStr = strtolower(substr($subject, 0, 4));
                    $select_testStr = strtolower(substr($subject_select, 0, 4));

                    if ($select_testStr == "fwd:" || $testStr == "fwd:") {
                        $subject = trim(substr($subject, strlen("fwd:")));
                        $subject_select = trim(substr($subject_select, strlen("fwd:")));
                        $found = true;
                    } //if

                    //bls:
                    $testStr = strtolower(substr($subject, 0, 4));
                    $select_testStr = strtolower(substr($subject_select, 0, 4));

                    if ($select_testStr == "bls:" || $testStr == "bls:") {
                        $subject = trim(substr($subject, strlen("bls:")));
                        $subject_select = trim(substr($subject_select, strlen("bls:")));
                        $found = true;
                    }

                    writeLog("[SALMA DEBUG $botId RESUBJECT 4] $subject : $subject_select");

                    if (!$found) {
                        $clean = true;
                    } //if
                } //while

                if ($subject_select != "" || $subject != "") {
                    //tambahkan group berdasar subject
                    if (in_array($emailJson->email, ['ahmadnurbrasta@gmail.com', 'irvansav28@gmail.com', 'ahmadnurbrasta@outlook.com'])) {
                        $query = $query . 'and ticket_group = "' . preg_replace('/\\\\u([0-9a-fA-F]{4})/', '\\\\\\\\u$1', $subject) . '" ';
                    } else {
                        $query = $query . "and ticket_group = '" . mysqli_real_escape_string($mysqli, $subject_select ?? $subject) . "' ";
                    }
                } //if
            } elseif ($chatType == "IGCOMMENT" ||
                $chatType == "FBCOMMENT" ||
                $chatType == "TWITTERCOMMENT" ||
                $chatType == "YTCOMMENT" ||
                $chatType == "GOOGLEPLAY" ||
                $chatType == "GOOGLEBUSINESS" ||
                $chatType == "SHOPEE" ||
                $chatType == "TOKOPEDIA"
            ) {
                //khusus comment, ticket dibedakan dari post id
                $commentJson = json_decode($chatMessage);
                // echo $chatMessage."<BR>";

                $postId = $commentJson->postId;

                if (!empty($postId)) {
                    //tambahkan group berdasar post id
                    $query = "SELECT ticket_status, ticket_idx, creation_date, chat_log_id_start, chat_log_idx_start, chat_log_id_end FROM botika_helpdesk_tickets WHERE " .
                        "bot_id = '" . $botId . "' and ticket_status != 'pending'  and ticket_status != 'solved' ";

                    $query = $query . 'AND ticket_group = "' . mysqli_real_escape_string($mysqli, $postId) . '" ' .

                    //per 19 maret 2021, tambah reqeust dari andri
                    $query = $query . "AND user_id = '" . mysqli_real_escape_string($mysqli, $userId) . "' ";
                } //if
            } else {
                $bypassSubject = true;
            }

            $query = $query . "order by ticket_idx desc limit 1";

            // echo $query."<BR>";
            // exit();

            $result = mysqli_query($mysqli, $query);
            $db_field = mysqli_fetch_assoc($result);
            writeLog("[SALMA DEBUG $botId RESUBJECT GET 2] $query " . json_encode($db_field));
            $ticketIdx = $db_field["ticket_idx"];
            $ticketCreationDate = $db_field["creation_date"];
            $ticketId = $db_field["ticket_id"];
            $ticketStatus = strtolower($db_field["ticket_status"]);
            $ticketAssigneeId = $db_field["ticket_assignee_id"];

            //if(($groupBySubject=="true") || ($groupBySubject===true) || ($groupBySubject=="1")) {
            if ($groupBySubject) {
                //do none, query utk email sdh group by subject
            } else {
                //per 28 des 18 semua email masuk jadi tiket baru
                if ($chatType == "EMAIL" || $chatType == "EMAILOUTLOOK") {$ticketStatus = "";}
                // if( $chatType=="IGCOMMENT"  || $chatType=="FBCOMMENT" || $chatType=="TWITTERCOMMENT" ) { $ticketStatus = ""; }
            } //if

            // echo "ticketStatus:".$ticketStatus."<BR>";
            // $ticketStatus = "";
            // exit;

            writeLog("[SALMA DEBUG $botId INFO]: ".json_encode(['groupBySubject' => $groupBySubject, 'ticketStatus' => $ticketStatus, 'ticketStatus' => $ticketStatus]));

            if (
                ($ticketStatus == "") ||
                ($ticketStatus == "pending") ||
                ($ticketStatus == "solved") ||
                ($ticketStatus == "closed")
            ) {
                // exit;

                // chatLogIdEnd helpdesk sebelumnya (bila ada)
                $chatLogIdPrev = $db_field["chat_log_id_start"];
                $chatLogIdxPrev = $db_field["chat_log_idx_start"];
                $chatLogIdEnd = $db_field["chat_log_id_end"];

                if ($chatLogIdEnd != "") {
                    //cari tanggal chat log id end tsb
                    $query = "SELECT creation_date FROM botika_chat_logs WHERE " .
                        "chat_log_id = '" . $chatLogIdEnd . "' ";

                    $result = mysqli_query($mysqli, $query);
                    $db_field = mysqli_fetch_assoc($result);

                    $chatLogIdEndDate = date("Y-n-j H:i:s", strtotime($db_field["creation_date"]));
                } //if
                //---------------------------------------------------------------------
                //cari chat log id start untuk chat id ini
                $query = "SELECT chat_log_id, chat_log_idx, message FROM botika_chat_logs WHERE " .
                    "chat_id = '" . $chatId . "' and message_from = 'User' ";

                if ($chatLogIdEnd != "") {
                    $query = $query . " and creation_date > '" . $chatLogIdEndDate . "' ";
                } //if

                $query = $query . " order by chat_log_idx asc "; //limit 0,10";
                // echo $query;

                $result = mysqli_query($mysqli, $query);

                if (mysqli_num_rows($result) == 0) {

                    // tidak ketemu chat log id awal, sleep dulu, kemungkinan karena belum tertulis di messagelog
                    sleep(2);
                    $query = "SELECT chat_log_id, chat_log_idx, message FROM botika_chat_logs WHERE chat_id = '" . $chatId . "' ";
                    if (isset($json["followup"]) && $json["followup"] && $chatLogIdEnd != "") {
                        $query .= " and creation_date > '" . $chatLogIdEndDate . "' ";
                    }
                    $query .= "ORDER BY chat_log_idx ASC LIMIT 0,1;";
                    $result = mysqli_query($mysqli, $query);

                } // if

                //writeLog($query, "logHelpdeskTicket");
                while ($db_field = mysqli_fetch_assoc($result)) {

                    $subjectMatch = false;

                    //check apakah subjectnya sama
                    $tempMsg = $db_field["message"];
                    $tempJson = json_decode($tempMsg, true);

                    // echo $tempJson["subject"].":".$subject."<BR>";

                    if ($subject == "") {
                        $subjectMatch = true;
                    } else {
                        if (strstr(strtolower($tempJson["subject"]), strtolower($subject))) {
                            $subjectMatch = true;
                        } elseif (strstr(strtolower($tempJson["postId"]), strtolower($postId))) {
                            $subjectMatch = true;
                        } //if
                    } //if

                    if ($subjectMatch === true || $subjectMatch === "true" || $bypassSubject == true) {

                        $chatLogIdStart = $db_field["chat_log_id"];
                        $chatLogIdxStart = $db_field["chat_log_idx"];
                        //check apakah ada duplikat
                        $query1 = 'select ticket_idx from botika_helpdesk_tickets ' .
                            'where chat_log_id_start = "' . $chatLogIdStart . '" ';
                        $result1 = mysqli_query($mysqli, $query1);

                        if (mysqli_num_rows($result1) == 0) {
                            break;
                        } //if
                    } //if($subjectMatch==true) {
                } //while

                // if ($emailJson->email == 'ahmadnurbrasta@gmail.com') {
                    writeLog("[SALMA DEBUG $botId CHATLOGIDSTART] from: " . ($emailJson->email ?? '') ." subject: ". ($subject ?? '') . " chatLogIdStart: ". ($chatLogIdStart ?? ''));
                // }
                
                // per 15 okt 2024, khusus email double check jika kosong berdasarkan subject
                if ($chatLogIdStart == "" && ($chatType == "EMAIL" || $chatType == "EMAILOUTLOOK") && isset($subject) && !empty($subject)) {

                    if (in_array($emailJson->email, ['ahmadnurbrasta@gmail.com', 'irvansav28@gmail.com', 'ahmadnurbrasta@outlook.com'])) {
                        writeLog("[SALMA DEBUG $botId 1] $json[subject]");
                        $no = 0;
                        while ($chatLogIdStart == "") {
                            $query1 = 'select chat_log_id, chat_log_idx from botika_chat_logs where user_id = "' . $userId . '" and bot_id = "' . $botId . '" and JSON_EXTRACT(message, \'$.subject\') = "'. addslashes($json['subject']) .'" ORDER by chat_log_idx DESC LIMIT 0, 1';
                            writeLog("[SALMA DEBUG $botId 2] $no - $query1");
                            $result1 = mysqli_query($mysqli, $query1);
                            if(mysqli_num_rows($result1) !== 0) {
                                $db_field1 = mysqli_fetch_assoc($result1);
                                $chatLogIdStart = $db_field1["chat_log_id"];
                                $chatLogIdxStart = $db_field1["chat_log_idx"];
                            }
                            $no++;
                            if ($no > 6) {
                                break;
                            } else {
                                usleep(500);
                            }
                        }
                        writeLog("[SALMA DEBUG $botId 3] $chatLogIdStart");
                    } else {
                        $query1 = 'select chat_log_id, chat_log_idx from botika_chat_logs where user_id = "' . $userId . '" and bot_id = "' . $botId . '" and JSON_EXTRACT(message, \'$.subject\') = "'. addslashes($json['subject']) .'" ORDER by chat_log_idx DESC LIMIT 0, 1';
                        writeLog("[SALMA DEBUG $botId 2] $query1");
                        $result1 = mysqli_query($mysqli, $query1);
                        if(mysqli_num_rows($result1) !== 0) {
                            $db_field1 = mysqli_fetch_assoc($result1);
                            $chatLogIdStart = $db_field1["chat_log_id"];
                            $chatLogIdxStart = $db_field1["chat_log_idx"];
                        }
                        writeLog("[SALMA DEBUG $botId 3] $chatLogIdStart");
                    }
                    //     // cari chatlog berdasarkan subject 
                    //     $query1 = 'select chat_log_id, chat_log_idx from botika_chat_logs where user_id = "' . $userId . '" and bot_id = "' . $botId . '" and message like "%'.mysqli_real_escape_string($mysqli, addslashes($subject)).'%" ORDER by chat_log_idx DESC LIMIT 0, 1';
                        
                    //     $result1 = mysqli_query($mysqli, $query1);
                    //     if(mysqli_num_rows($result1) !== 0) {
                    //         $db_field1 = mysqli_fetch_assoc($result1);
                    //         $chatLogIdStart = $db_field1["chat_log_id"];
                    //         $chatLogIdxStart = $db_field1["chat_log_idx"];
                    //     } else {
                    //         $query2 = 'select chat_log_id, chat_log_idx from botika_chat_logs where user_id = "' . $userId . '" and bot_id = "' . $botId . '" and message like "%'.mysqli_real_escape_string($mysqli, $subject).'%" ORDER by chat_log_idx DESC LIMIT 0, 1';
                        
                    //         $result2 = mysqli_query($mysqli, $query2);
                    //         if(mysqli_num_rows($result1) !== 0) {
                    //             $db_field2 = mysqli_fetch_assoc($result2);
                    //             $chatLogIdStart = $db_field2["chat_log_id"];
                    //             $chatLogIdxStart = $db_field2["chat_log_idx"];
                    //         }
                    //     }
                    // }

                }
                
                //per 12 nov 2020 double check, bila tetap kosong, maka assign yg terakhir
                if ($chatLogIdStart == "") {
                    $chatLogIdStart = $db_field["chat_log_id"];
                    $chatLogIdxStart = $db_field["chat_log_idx"];
                } //if
                //per 5 feb 2021 double check, bila tetap kosong, maka assign yg chat log id start yg lalu
                if ($chatLogIdStart == "") {
                    $chatLogIdStart = $chatLogIdPrev;
                } //if

                // echo "chatLogIdStart:".$chatLogIdStart."<BR>";
                // exit;
                //---------------------------------------------------------------------
                // $ticketId = ""; //fungsi

                $ticketGroupVal = "";
                if (($chatType == "EMAIL" || $chatType == "EMAILOUTLOOK") && isset($subject) && !empty($subject)) {
                    $ticketGroupVal = preg_replace('/\\\\u([0-9a-fA-F]{4})/', '\\\\\\\\u$1', $subject);
                    writeLog("[SALMA DEBUG $botId SUBJECT 3] $json[subject] : $subject : " . $ticketGroupVal);
                } elseif (($chatType == "IGCOMMENT" ||
                    $chatType == "FBCOMMENT" ||
                    $chatType == "TWITTERCOMMENT" ||
                    $chatType == "YTCOMMENT" ||
                    $chatType == "GOOGLEPLAY" ||
                    $chatType == "GOOGLEBUSINESS" ||
                    $chatType == "SHOPEE" ||
                    $chatType == "TOKOPEDIA"
                )
                    && isset($postId)
                    && !empty($postId)) {
                    $ticketGroupVal = $postId;
                } else if ($subject) {
                    $ticketGroupVal = $subject;
                }

                // 1 Feb 24, tambah lock create ticket
                $lockAssign = checkLock2("helpdeskticketcreate_$botId-$userId-$chatType-$ticketGroupVal", 5, 1);
                // var_dump($lockAssign); die;
                if (!$lockAssign) {
                    echo '{"result":"error","msg":"Already process ticket"}';
                    writeLog("[$uniqode - helpdeskticketcreate] Ticket: $botId-$userId already request create ticket, terminate", "logDebug_" . date("Y-m-d_H"));

                } else {
                    $key = true;
                    while ($key) {
                        $ticketId = 'hdt' . $botId . randString();
                        $query = "select ticket_idx from botika_helpdesk_tickets where ticket_id = '" . $ticketId . "'";
                        $checkProductId = mysqli_query($mysqli, $query);
                        if (mysqli_num_rows($checkProductId) < 1) {
                            $key = false;
                        }
                    }

                    //$ticketGroupVal = "";

                    //kalau accountId tidak kosong, maka langsung assign ke agent tsb
                    //echo $accountId;
                    //exit;

                    if ($accountId != "") {

                        //per 31 agustus 2021
                        //check dulu apakah agent offline
                        //bila agent offline dan tidak mau wait, maka set sbg error

                        $agentOnline = true;

                        //cari semua account_id yang online untuk bot ini
                        $query = "select bba.*, ba.login, ba.login_status, " .
                            "TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) last_active " .
                            " from botika_bot_access bba, botika_accounts ba " .
                            " where ba.account_id = bba.account_id " .
                            " and ba.login = '1' " .
                            " and (level = '' or level is null or level = 'agent') " .
                            " and (ba.login_status = '' or ba.login_status = 'available' or ba.login_status is null) " .
                            " and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32 " .
                            " and bot_id = '" . $botId . "' and ba.account_id = '" . $accountId . "' ";

                        $result = mysqli_query($mysqli, $query);
                        if (mysqli_num_rows($result) == 0) {
                            //tidak ketemu / offline
                            $agentOnline = false;
                        } //if

                        if (
                            ($agentOnline == false) &&
                            ($waitOk === false)
                        ) {
                            $send_message = false;
                            echo '{"result":"error","msg":"Agent offline"}';
                            writeLog("[$uniqode - helpdeskticketcreate] Returning agent offline", "logDebug_" . date("Y-m-d_H"));
                        } else {
                            writeLock("helpdeskticketcreate_$botId-$userId-$chatType-$ticketGroupVal");
                            $creationDate = date("Y-n-j H:i:s");

                            // if ($emailJson->email == 'ahmadnurbrasta@gmail.com') {
                                $query = "insert into botika_helpdesk_tickets(ticket_id, ticket_assignee_id, ticket_status, chat_log_id_start, chat_log_idx_start, chat_id, ticket_group, ticket_category, chat_type2, " .
                                "user_id, bot_id, department_id, workspace_id, creation_date) values(" .
                                "'" . $ticketId . "', " .
                                "'" . $accountId . "', " .
                                "'unsolved', " .
                                "'" . $chatLogIdStart . "', " .
                                "'" . $chatLogIdxStart . "', " .
                                "'" . $chatId . "', " .
                                '"' . $ticketGroupVal . '", ' .
                                "'" . mysqli_real_escape_string($mysqli, $ticketCategory) . "', " .
                                    "'" . $chatType . "', " .
                                    "'" . $userId . "', '" . $botId . "', " .
                                    "'" . $departmentId . "', " .
                                    "'" . $workspaceId . "', " .
                                    "'" . $creationDate . "')";
                            // } else {
                            //     $query = "insert into botika_helpdesk_tickets(ticket_id, ticket_assignee_id, ticket_status, chat_log_id_start, chat_log_idx_start, chat_id, ticket_group, ticket_category, chat_type2, " .
                            //     "user_id, bot_id, department_id, workspace_id, creation_date) values(" .
                            //     "'" . $ticketId . "', " .
                            //     "'" . $accountId . "', " .
                            //     "'unsolved', " .
                            //     "'" . $chatLogIdStart . "', " .
                            //     "'" . $chatLogIdxStart . "', " .
                            //     "'" . $chatId . "', " .
                            //     "'" . mysqli_real_escape_string($mysqli, $ticketGroupVal) . "', " .
                            //     "'" . mysqli_real_escape_string($mysqli, $ticketCategory) . "', " .
                            //         "'" . $chatType . "', " .
                            //         "'" . $userId . "', '" . $botId . "', " .
                            //         "'" . $departmentId . "', " .
                            //         "'" . $workspaceId . "', " .
                            //         "'" . $creationDate . "')";
                            // }

                            // echo "1:".$query;
                            // exit;
                            
                            
                            $result = mysqli_query($mysqli, $query);
                            $lastid = mysqli_insert_id($mysqli);
                            writeLog("[SALMA DEBUG $botId INSERT 1] $query " . json_encode($lastid));
                            //-----------------------------------
                            $send_message = true;

                            $ticketIdx = $lastid;
                            $ticketCreationDate = $creationDate;

                            //agent info
                            // $queryAgent2 = 'select ba.account_id, ba.username from botika_accounts ba where ba.account_id  = "'.$accountId.'" ';
                            // $resultAgent2 = mysqli_query($mysqli, $queryAgent2);
                            // $agentData2 = $resultAgent2->fetch_all(MYSQLI_ASSOC);

                            // $agent = $agentData2[0]['account_id'];
                            // $agentName = $agentData2[0]['username'];

                            echo '{"result":"success", "ticketId":"' . $ticketId . '","accountId":"' . $accountId . '"}';

                            writeLog("[$uniqode - helpdeskticketcreate] Returning " . '{"result":"success", "ticketId":"' . $ticketId . '","accountId":"' . $accountId . '"}', "logDebug_" . date("Y-m-d_H"));

                        } //if

                    } else {

                        //cari setting berapa limit per agent
                        $query = "select * from botika_bot_settings where bot_id = '" . $botId . "' ";
                        $result = mysqli_query($mysqli, $query);

                        if (mysqli_num_rows($result) == 0) {

                            $agentTransfer = "manual";
                            $ticketPerAgent = 0;

                        } else {
                            $db_field = mysqli_fetch_assoc($result);

                            $agentTransfer = $db_field["agent_transfer"];
                            $ticketPerAgent = sanitize_int($db_field["ticket_per_agent"]);

                            // per 22 januari, cari limit per channel
                            if ($workspaceId == "") {
                                // cari id workspace
                                $query1 = "SELECT workspace_id FROM botika_omny_workspace_bots WHERE bot_id = '" . $botId . "'";
                                // echo $query1;
                                $result1 = mysqli_query($mysqli, $query1);

                                $db_field1 = mysqli_fetch_assoc($result1);

                                $workspaceId = $db_field1["workspace_id"];

                            } //if

                            // echo "workspaceId:".$workspaceId;
                            // exit;

                            if ($workspaceId != "") {

                                // cari setting
                                $query1 = "SELECT * FROM botika_omny_workspace_settings WHERE workspace_id = '" . $workspaceId . "'";
                                $result1 = mysqli_query($mysqli, $query1);

                                $db_field1 = mysqli_fetch_assoc($result1);

                                // {"sla":[{"create":"system","status":"1","name":"Default SLA Policy","description":"default policy","priority":{"urgent":{"respond":{"value":"10","unit":"minute"},"resolve":{"value":"1","unit":"hour"},"operation":"business","email":"urgent"},"high":{"respond":{"value":"30","unit":"minute"},"resolve":{"value":"3","unit":"hour"},"operation":"business","email":"high"},"medium":{"respond":{"value":"60","unit":"minute"},"resolve":{"value":"6","unit":"hour"},"operation":"business","email":"medium"},"low":{"respond":{"value":"2","unit":"hour"},"resolve":{"value":"1","unit":"day"},"operation":"business","email":"low"}}}],"timezone":"Asia\/Jakarta","get_start":{"workspace_verified":true,"invite_team":false,"ticket":true,"integration":false},"agent":{"menu":{"access":"limited"},"transfer":"auto","ticket":{"access":"ticket only","total":"1","take":"false","channels":[{"name":["CHATBOTIKAWEBCHAT","BOTIKAWEBCHAT"],"limit":"127"},{"name":["TWITTER"],"limit":"127"},{"name":["WHATSAPP","OAWHATSAPP","OAWAPPIN","OAWHATSAPPJATIS","OAWHATSAPPTWILIO","OAWHATSAPPDAMCORP"],"limit":"127"},{"name":["TWITTERCOMMENT"],"limit":"127"},{"name":["EMAIL"],"limit":"127"},{"name":["TELEGRAM"],"limit":"127"},{"name":["FBMESSENGER"],"limit":"127"},{"name":["KAKAOTALK"],"limit":"127"},{"name":["FBCOMMENT"],"limit":"127"},{"name":["WECHAT"],"limit":"127"},{"name":["IGCOMMENT"],"limit":"127"},{"name":["ZALOOA"],"limit":"127"},{"name":["LINE"],"limit":"127"},{"name":["VIBER"],"limit":"127"},{"name":["ELIZA","PHONE"],"limit":"1","pauseOnCall":"true"},{"name":["WEBHOOK"],"limit":"127"}]}},"timework":{"sunday":[],"monday":[["08:00","17:00"]],"tuesday":[["08:00","17:00"]],"wednesday":[["08:00","17:00"]],"thursday":[["08:00","17:00"]],"friday":[["08:00","17:00"]],"saturday":[]}}
                                $workspaceSetting = $db_field1["setting"];

                                //cari limit channel ini, bila ada, maka assign ke ticketPerAgent
                                $workspaceSetting = json_decode($workspaceSetting, true);
                                // print_r($workspaceSetting);

                                if (isset($workspaceSetting["agent"]["ticket"]["channels"])) {

                                    for ($i = 0; $i < sizeof($workspaceSetting["agent"]["ticket"]["channels"]); $i++) {

                                        for ($j = 0; $j < sizeof($workspaceSetting["agent"]["ticket"]["channels"][$i]["name"]); $j++) {

                                            if (strtolower($workspaceSetting["agent"]["ticket"]["channels"][$i]["name"][$j]) == "phone") {

                                                // khusus phone, check "pauseOnCall":"true"
                                                // {"name":["ELIZA","PHONE"],"limit":"1","pauseOnCall":"true"}
                                                if (strtoupper($workspaceSetting["agent"]["ticket"]["channels"][$i]["pauseOnCall"]) == "TRUE") {
                                                    $pauseOnCall = true;
                                                } //if

                                            } //if

                                            if (
                                                (strtolower($workspaceSetting["agent"]["ticket"]["channels"][$i]["name"][$j]) == strtolower($chatType)) &&
                                                (sanitize_int($workspaceSetting["agent"]["ticket"]["channels"][$i]["limit"]) > 0)
                                            ) {
                                                $ticketPerAgent = sanitize_int($workspaceSetting["agent"]["ticket"]["channels"][$i]["limit"]);
                                                writeLog("[$uniqode - helpdeskticketcreate] Get new limit :$chatType limit: $ticketPerAgent bot: $botId - ", "logDebug_" . date("Y-m-d_H"));
                                                break 2;
                                            } //if

                                        } //for
                                    } //for
                                } //if

                            } //if
                        } //if

                        // echo $ticketPerAgent;
                        // exit;

                        // echo "agentTransfer:".$agentTransfer."<BR>";
                        // exit;

                        if ($agentTransfer == "manual") {
                            //$trxItem = mysqli_real_escape_string($mysqli, $json["trxitem);
                            $creationDate = date("Y-n-j H:i:s");
                            writeLock("helpdeskticketcreate_$botId-$userId-$chatType-$ticketGroupVal");
                            // if ($emailJson->email == 'ahmadnurbrasta@gmail.com') {
                                $query = "insert into botika_helpdesk_tickets(ticket_id, ticket_status, chat_log_id_start, chat_log_idx_start, chat_id, " .
                                "ticket_group, ticket_category, " .
                                "user_id, bot_id, department_id, workspace_id, chat_type2, creation_date) values(" .
                                "'" . $ticketId . "', " .
                                "'unassigned', " .
                                "'" . $chatLogIdStart . "', " .
                                "'" . $chatLogIdxStart . "', " .
                                "'" . $chatId . "', " .
                                '"' . $ticketGroupVal . '", ' .
                                "'" . mysqli_real_escape_string($mysqli, $ticketCategory) . "', " .
                                    "'" . $userId . "', '" . $botId . "', " .
                                    "'" . $departmentId . "', " .
                                    "'" . $workspaceId . "', " .
                                    "'" . $chatType . "', " .
                                    "'" . $creationDate . "')";
                            // } else {
                            //     $query = "insert into botika_helpdesk_tickets(ticket_id, ticket_status, chat_log_id_start, chat_log_idx_start, chat_id, " .
                            //     "ticket_group, ticket_category, " .
                            //     "user_id, bot_id, department_id, workspace_id, chat_type2, creation_date) values(" .
                            //     "'" . $ticketId . "', " .
                            //     "'unassigned', " .
                            //     "'" . $chatLogIdStart . "', " .
                            //     "'" . $chatLogIdxStart . "', " .
                            //     "'" . $chatId . "', " .
                            //     "'" . mysqli_real_escape_string($mysqli, $ticketGroupVal) . "', " .
                            //     "'" . mysqli_real_escape_string($mysqli, $ticketCategory) . "', " .
                            //         "'" . $userId . "', '" . $botId . "', " .
                            //         "'" . $departmentId . "', " .
                            //         "'" . $workspaceId . "', " .
                            //         "'" . $chatType . "', " .
                            //         "'" . $creationDate . "')";
                            // }

                            // echo "2:".$query;
                            // exit;

                            $result = mysqli_query($mysqli, $query);
                            //-----------------------------------

                            $lastid = mysqli_insert_id($mysqli);
                            writeLog("[SALMA DEBUG $botId INSERT 2] $query " . json_encode($lastid));

                            $ticketIdx = $lastid;
                            $ticketCreationDate = $creationDate;

                            $send_message = true;

                            echo '{"result":"success", "ticketId":"' . $ticketId . '"}';
                            writeLog("[$uniqode - helpdeskticketcreate] Returning " . '{"result":"success", "ticketId":"' . $ticketId . '"}', "logDebug_" . date("Y-m-d_H"));
                        } else {

                            $arrAgent = array();

                            //cari semua account_id yang online untuk bot ini
                            // $query = "select bba.*, ba.login, ba.login_status, ".
                            // "TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) last_active ".
                            // " from botika_bot_access bba, botika_accounts ba ".
                            // " where ba.account_id = bba.account_id ".
                            // " and ba.login = '1' ".
                            // " and (level = '' or level is null or level = 'agent') ".
                            // " and (ba.login_status = '' or ba.login_status = 'available' or ba.login_status is null) ".
                            // " and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32 ".
                            // " and bot_id = '".$botId."' ";

                            // per 28 Des 2022, tambahkan pengecekan ticket solve per day
                            $query = "
								SELECT DISTINCT(ba.account_id) account_id, bba.*, ba.login, ba.login_status, ba.last_active_date last_active, oml.value `channels`, tbl.total_solved
								from botika_bot_access bba
								join botika_accounts ba on ba.account_id = bba.account_id
								left join (
									select ticket_assignee_id, COUNT(ticket_idx) AS total_solved
									from botika_helpdesk_tickets
									where ticket_status = 'solved' and bot_id = '$botId'
									and creation_date >= '" . date("Y-m-d 00:00:00") . "'
									group BY ticket_assignee_id
								) tbl ON bba.account_id = tbl.ticket_assignee_id
                                left join botika_omny_multi_level_values oml on oml.agent_id = ba.account_id and oml.bot_id = '$botId' and oml.type = 'channel'
								where bba.bot_id = '$botId' AND login = 1 and
								(bba.level = '' or bba.level is null or bba.level = 'agent') and
								(login_status = '' or login_status = 'available' or login_status is null) and
								TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32";

                            // echo $query."<BR>";
                            // exit;

                            $result = mysqli_query($mysqli, $query);

                            if (mysqli_num_rows($result) == 0) {

                                //do none
                                // echo $query."<BR>";
                                // exit;

                            } else {

                                while ($db_field = mysqli_fetch_assoc($result)) {

                                    //check channel
                                    //kalau kosong = all
                                    //kalau {} = none

                                    $chanPermissionOk = false;
                                    $departmentPermissionOk = true;

                                    //echo $departmentId;
                                    //exit;

                                    if ($departmentId != "") {

                                        // check apakah agent ada di department ini
                                        $query1 = "SELECT departement_id FROM botika_omny_workspace_access " .
                                            "WHERE workspace_id = '" . $workspaceId . "' AND " .
                                            "departement_id = '" . $departmentId . "' AND " .
                                            "account_id = '" . $db_field["account_id"] . "' ";
                                        $result1 = mysqli_query($mysqli, $query1);

                                        // echo $query1."<BR>";
                                        // exit;

                                        if (mysqli_num_rows($result1) == 0) {
                                            $departmentPermissionOk = false;
                                        } else {
                                            $departmentPermissionOk = true;
                                        } //if

                                    } //if

                                    //echo "departmentPermissionOk:".$departmentPermissionOk;
                                    //exit;

                                    if ($departmentPermissionOk == true) {
                                        /*
                                        // check apakah agent punya skill channel ini
                                        $query1 = "select value from botika_omny_multi_level_values " .
                                        "where agent_id = '" . $db_field["account_id"] . "' and type = 'channel' and bot_id = '$botId'";
                                        $result1 = mysqli_query($mysqli, $query1);

                                        //echo $query1."<BR>";
                                        //exit;

                                        if (mysqli_num_rows($result1) == 0) {
                                        $chanPermissionOk = true;
                                        } else {

                                        $db_field1 = mysqli_fetch_assoc($result1);

                                        $chanPermission = $db_field1["value"];

                                        if ($chanPermission == "") {
                                        //all
                                        $chanPermissionOk = true;
                                        } else if (
                                        ($chanPermission == "{}") ||
                                        ($chanPermission == "[]")
                                        ) {
                                        //none
                                        $chanPermissionOk = false;
                                        } else {
                                        $chanPermission = json_decode($chanPermission, true);

                                        for ($m = 0; $m < sizeof($chanPermission); $m++) {
                                        // echo strtolower($chanPermission[$m]).":".strtolower($chatType)."<BR>";
                                        if (strtolower($chanPermission[$m]) == strtolower($chatType)) {
                                        $chanPermissionOk = true;
                                        break; //$m
                                        } //if
                                        } //for
                                        } //if
                                        } //if
                                         */
                                        if ($db_field["channels"] == '') {
                                            $chanPermissionOk = true;
                                        } else if (
                                            ($db_field["channels"] == "{}") ||
                                            ($db_field["channels"] == "[]")
                                        ) {
                                            //none
                                            $chanPermissionOk = false;
                                        } else {
                                            $chanPermission = json_decode($db_field["channels"], true);

                                            for ($m = 0; $m < sizeof($chanPermission); $m++) {
                                                // echo strtolower($chanPermission[$m]).":".strtolower($chatType)."<BR>";
                                                if (strtolower($chanPermission[$m]) == strtolower($chatType)) {
                                                    $chanPermissionOk = true;
                                                    break; //$m
                                                } //if
                                            } //for
                                        }

                                        // Check apakah channel telepon dan agen memiliki extension
                                        //---------------------------------------------------------
                                        $allowMe = true;
                                        if (
                                            ($chatType == "PHONE")
                                        ) {
                                            // cari extension
                                            $query = "select value from botika_omny_multi_level_values " .
                                                "where agent_id = '" . $db_field["account_id"] . "' and type = 'extensions'";
                                            $result_phone = mysqli_query($mysqli, $query);

                                            if (mysqli_num_rows($result_phone) == 0) {
                                                $allowMe = false;
                                                //tidak ada extension
                                            } else {
                                                $allowMe = true;
                                            } //if
                                        } //if

                                        if ($chanPermissionOk && $allowMe) {
                                            $arrAgent[] = array(
                                                "accountId" => $db_field["account_id"],
                                                "total" => 0,
                                                "totalSolved" => (int) $db_field["total_solved"] ?? 0,
                                                "lastActiveDate" => $db_field["last_active"],
                                            );
                                        } //if

                                    } //if

                                } //while

                                // exit;
                                //per 22 januari limit berdasar total channel
                                if (isset($workspaceSetting["agent"]["ticket"]["channels"])) {

                                    $query = "select ticket_assignee_id, botika_chat_logs.chat_type, COUNT(ticket_assignee_id) AS total, botika_helpdesk_tickets.creation_date " .
                                        "from botika_helpdesk_tickets " .
                                        "LEFT JOIN botika_chat_logs ON botika_helpdesk_tickets.chat_log_id_start = botika_chat_logs.chat_log_id " .
                                        "where ticket_status = 'unsolved' and botika_helpdesk_tickets.bot_id = '" . $botId . "' " .
                                        "and ticket_assignee_id != '' " .
                                        "GROUP BY ticket_assignee_id, botika_chat_logs.chat_type " .
                                        "ORDER BY ticket_assignee_id asc";

                                    // echo $query;

                                    $result = mysqli_query($mysqli, $query);

                                    if (mysqli_num_rows($result) == 0) {

                                        //do none
                                        //per 17 maret

                                        if (sizeof($arrAgent) > 1) {

                                            //tiket kosong, kasih ke yang paling sedikit tiketnya
                                            $query = "select ticket_assignee_id, ticket_solved_date from botika_helpdesk_tickets " .
                                                "WHERE bot_id = '" . $botId . "' AND (";

                                            for ($k = 0; $k < sizeof($arrAgent); $k++) {

                                                if ($k > 0) {$query = $query . " OR ";}

                                                $query = $query .
                                                    "ticket_assignee_id = '" . $arrAgent[$k]["accountId"] . "'";
                                            } //for

                                            $query = $query . ") AND " .
                                            "(creation_date >= '" . date("Y-n-j 00:00:00") . "' AND creation_date <= '" . date("Y-n-j 23:59:59") . "') " .
                                                "ORDER BY ticket_solved_date DESC LIMIT 0,1;";

                                            // echo $query;

                                            $result = mysqli_query($mysqli, $query);

                                            while ($db_field = mysqli_fetch_assoc($result)) {

                                                if ($db_field["ticket_assignee_id"] != "") {

                                                    for ($k = 0; $k < sizeof($arrAgent); $k++) {

                                                        if ($arrAgent[$k]["accountId"] == $db_field["ticket_assignee_id"]) {

                                                            //temporer agar tidak diberi tiket
                                                            $arrAgent[$k]["total"] = 9999;
                                                            $arrAgent[$k]["totalAllChannel"] = 9999;
                                                            $arrAgent[$k]["ticket_date"] = strtotime($db_field["creation_date"]);

                                                        } //if

                                                    } //for($k=0;$k<sizeof($arrAgent);$k++) {
                                                }

                                            } //while

                                            // if($botId=="bcsx3oz7ysx") {
                                            //     writeLog("[".date("Y-n-j H:i:s")."] 1:".$workspaceId.":".$departmentId.":".$chatType, "logRobin_".$rndStr);
                                            //     writeLog("[".date("Y-n-j H:i:s")."] 1:".$query, "logRobin_".$rndStr);
                                            //     writeLog("[".date("Y-n-j H:i:s")."] 1:".print_r($arrAgent, true), "logRobin_".$rndStr);
                                            // }

                                        } //if(sizeof($arrAgent)>1) {

                                    } else {

                                        while ($db_field = mysqli_fetch_assoc($result)) {

                                            if ($db_field["ticket_assignee_id"] != "") {

                                                for ($k = 0; $k < sizeof($arrAgent); $k++) {

                                                    // echo $db_field["chat_type"]."<BR>";
                                                    // echo $arrAgent[$k]["accountId"].":".$db_field["ticket_assignee_id"]."<BR>";
                                                    if ($arrAgent[$k]["accountId"] == $db_field["ticket_assignee_id"]) {

                                                        if (strtolower($db_field["chat_type"]) == strtolower($chatType)) {
                                                            // echo "set total:".$db_field["total"]."<BR>";
                                                            $arrAgent[$k]["total"] = sanitize_int($db_field["total"]);
                                                            $arrAgent[$k]["ticket_date"] = strtotime($db_field["creation_date"]);
                                                        } //if

                                                        //tambahkan di totalAllChannel
                                                        $arrAgent[$k]["totalAllChannel"] =
                                                        sanitize_int($arrAgent[$k]["totalAllChannel"] ?? 0) +
                                                        sanitize_int($db_field["total"]);

                                                        // jika sudah lebih atau sama dgn ticketPerAgent, maka letakkan di paling bawah
                                                        if ($arrAgent[$k]["total"] >= $ticketPerAgent) {
                                                            $arrAgent[$k]["total"] = 9999;
                                                            $arrAgent[$k]["totalAllChannel"] = 9999;
                                                        } //if

                                                        //jika handle phone, dan pauseOnCall = true, maka anggap total penuh
                                                        if (
                                                            strtolower($db_field["chat_type"]) == "phone"
                                                        ) {
                                                            if ($pauseOnCall == true) {
                                                                $arrAgent[$k]["total"] = 9999;
                                                                $arrAgent[$k]["totalAllChannel"] = 9999;
                                                            } //if
                                                        } //if

                                                    } //if

                                                } //for($k=0;$k<sizeof($arrAgent);$k++) {
                                            }

                                        } //while

                                    } //if

                                    //--------------------------------
                                    //sort berdasar total tiket all channel
                                    // function cmp($a, $b)
                                    // {
                                    //     return $a["totalAllChannel"] - $b["totalAllChannel"];
                                    // } //function cmp($a, $b)

                                    // usort($arrAgent, "cmp");

                                    // per 17 maret 2022, bila jumlah sama, utamakan yg ticketnya paling lama
                                    // function cmp($a, $b){
                                    //     // $c = $a['totalAllChannel'] - $b['totalAllChannel'];
                                    //     // $c .= $a['ticket_date'] - $b['ticket_date'];
                                    //     $c = $a['totalAllChannel'] - $b['totalAllChannel'];
                                    //     $d = $a['ticket_date'] - $b['ticket_date'];
                                    //     $c = $d*10000000 + $c;
                                    //     return $c;
                                    // }
                                    // uasort($arrAgent, "cmp");

                                    // per 28 des 2022, ubah sorting menjadi triple sorting
                                    $arrAgent = array_orderby($arrAgent, 'totalAllChannel', SORT_ASC, 'totalSolved', SORT_ASC); // , 'ticket_date', SORT_ASC
                                    $arrAgent = array_values($arrAgent);

                                } else {

                                    //cari ticket yg sedang dihandle agent
                                    $query = "select bot_id, ticket_assignee_id, COUNT(ticket_assignee_id) AS total, botika_helpdesk_tickets.creation_date " .
                                        "from botika_helpdesk_tickets " .
                                        "where ticket_status = 'unsolved' and bot_id = '" . $botId . "' " .
                                        "and ticket_assignee_id != '' " .
                                        "group by bot_id, ticket_assignee_id";

                                    $result = mysqli_query($mysqli, $query);
                                    // echo $query;

                                    if (mysqli_num_rows($result) == 0) {

                                        //per 17 maret
                                        if (sizeof($arrAgent) > 1) {

                                            //tiket kosong, kasih ke yang paling sedikit tiketnya
                                            $query = "select ticket_assignee_id, ticket_solved_date from botika_helpdesk_tickets " .
                                                "WHERE bot_id = '" . $botId . "' AND (";

                                            for ($k = 0; $k < sizeof($arrAgent); $k++) {

                                                if ($k > 0) {$query = $query . " OR ";}

                                                $query = $query .
                                                    "ticket_assignee_id = '" . $arrAgent[$k]["accountId"] . "'";
                                            } //for

                                            $query = $query . ") AND " .
                                            "(creation_date >= '" . date("Y-n-j 00:00:00") . "' AND creation_date <= '" . date("Y-n-j 23:59:59") . "') " .
                                                "ORDER BY ticket_solved_date DESC LIMIT 0,1;";

                                            // echo $query;

                                            $result = mysqli_query($mysqli, $query);

                                            while ($db_field = mysqli_fetch_assoc($result)) {

                                                if ($db_field["ticket_assignee_id"] != "") {

                                                    for ($k = 0; $k < sizeof($arrAgent); $k++) {

                                                        if ($arrAgent[$k]["accountId"] == $db_field["ticket_assignee_id"]) {

                                                            //temporer agar tidak diberi tiket
                                                            $arrAgent[$k]["total"] = 9999;
                                                            $arrAgent[$k]["ticket_date"] = strtotime($db_field["creation_date"]);

                                                        } //if

                                                    } //for($k=0;$k<sizeof($arrAgent);$k++) {
                                                }

                                            } //while

                                            // if($botId=="bcsx3oz7ysx") {
                                            //     writeLog("[".date("Y-n-j H:i:s")."] 2:".$workspaceId.":".$departmentId.":".$chatType, "logRobin_".$rndStr);
                                            //     writeLog("[".date("Y-n-j H:i:s")."] 2:".$query, "logRobin_".$rndStr);
                                            //     writeLog("[".date("Y-n-j H:i:s")."] 2:".print_r($arrAgent, true), "logRobin_".$rndStr);
                                            // }

                                        } //if(sizeof($arrAgent)>1) {

                                    } else {
                                        while ($db_field = mysqli_fetch_assoc($result)) {

                                            if ($db_field["ticket_assignee_id"] != "") {
                                                for ($k = 0; $k < sizeof($arrAgent); $k++) {

                                                    if ($arrAgent[$k]["accountId"] == $db_field["ticket_assignee_id"]) {
                                                        $arrAgent[$k]["total"] = (int) $db_field["total"];
                                                        $arrAgent[$k]["ticket_date"] = strtotime($db_field["creation_date"]);
                                                    } //if

                                                } //for($k=0;$k<sizeof($arrAgent);$k++) {
                                            }

                                        } //while
                                    } //if

                                    //--------------------------------
                                    //sort
                                    // function cmp($a, $b)
                                    // {
                                    //     return $a["total"] - $b["total"];
                                    // } //function cmp($a, $b)

                                    // usort($arrAgent, "cmp");

                                    // per 17 maret 2022, bila jumlah sama, utamakan yg ticketnya paling lama
                                    function cmp($a, $b)
                                    {
                                        $c = $a['total'] - $b['total'];
                                        $d = $a['ticket_date'] - $b['ticket_date'];
                                        $c = $c * 10000000 + $d;
                                        // echo (int)$c.PHP_EOL.PHP_EOL;
                                        return $c;
                                    }

                                    uasort($arrAgent, "cmp");
                                    $arrAgent = array_values($arrAgent);

                                } //if

                            } //if(mysqli_num_rows($result) == 0){

                            //print_r($arrAgent);
                            //echo $ticketPerAgent."<BR>";
                            //exit;
                            // if($botId=="bcsx3oz7ysx") {
                            //     writeLog("[".date("Y-n-j H:i:s")."] 3:".print_r($arrAgent, true), "logRobin_".$rndStr);
                            // }
                            writeLog("[$uniqode - helpdeskticketcreate] Log round robin: $chatType limit: $ticketPerAgent bot: $botId - " . json_encode($arrAgent), "logDebug_" . date("Y-m-d_H"));
                            //--------------------------------
                            //bila ticket per agent lebih kecil dari agent yg paling sedikit menghandle ticket,
                            //maka lanjut
                            $send_message = true;

                            //cek sekali lagi apakah user ini pernah punya ticket untuk mengatasi api jalan bersamaan
                            //cari chat log id start untuk chat id ini
                            $query = "SELECT ticket_id, ticket_status, chat_log_id_end, ticket_assignee_id FROM botika_helpdesk_tickets WHERE " .
                                "bot_id = '" . $botId . "' AND user_id = '" . $userId . "' ";

                            $subject = "";
                            if ($chatType == "EMAIL" || $chatType == "EMAILOUTLOOK") {
                                //khusus email, ticket dibedakan dari subject
                                //{"msg":[{"text":"<meta http-equiv=\"Content-Type\" content=\"text\/html; charset=utf-8\"><div dir=\"ltr\">testing<\/div> <br>"}],"email":"Vads.sulistioa@xl.co.id","subject":"test1","replyto":"nura1068@gmail.com","cc":"","bcc":""}
                                $emailJson = json_decode($chatMessage);
                                
                                # 8 okt 24, cocokkan subject dengan yng dari chatlog, jika berbeda gunakan dari param
                                // if ($emailJson->email != 'ahmadnurbrasta@gmail.com') {
                                    if(strstr($ticketGroupVal, $emailJson->subject)) {
                                        $subject = $emailJson->subject;
                                    } else {
                                        $subject = $ticketGroupVal;
                                    }
                                // }

                                //bersihkan subject
                                $clean = false;

                                while (!$clean) {

                                    $found = false;

                                    //re:
                                    $testStr = strtolower(substr($subject, 0, 3));
                                    $select_testStr = strtolower(substr($subject_select, 0, 3));

                                    writeLog("[$uniqode - helpdeskticketcreate] [SALMA DEBUG $botId RESUBJECT 1] $subject : $subject_select");

                                    if ($select_testStr == "re:" || $testStr == "re:") {
                                        $subject = trim(substr($subject, strlen("re:")));
                                        $subject_select = trim(substr($subject_select, strlen("re:")));
                                        $found = true;
                                    } //if

                                    //fwd:
                                    $testStr = strtolower(substr($subject, 0, 4));
                                    $select_testStr = strtolower(substr($subject_select, 0, 4));

                                    if ($select_testStr == "fwd:" || $testStr == "fwd:") {
                                        $subject = trim(substr($subject, strlen("fwd:")));
                                        $subject_select = trim(substr($subject_select, strlen("fwd:")));
                                        $found = true;
                                    } //if

                                    //bls:
                                    $testStr = strtolower(substr($subject, 0, 4));
                                    $select_testStr = strtolower(substr($subject_select, 0, 4));

                                    if ($select_testStr == "bls:" || $testStr == "bls:") {
                                        $subject = trim(substr($subject, strlen("bls:")));
                                        $subject_select = trim(substr($subject_select, strlen("bls:")));
                                        $found = true;
                                    }

                                    writeLog("[$uniqode - helpdeskticketcreate] [SALMA DEBUG $botId RESUBJECT 2] $subject : $subject_select");

                                    if (!$found) {
                                        $clean = true;
                                    } //if
                                } //while

                                if ($subject_select != "" || $subject != "") {
                                    //tambahkan group berdasar subject
                                    if (in_array($emailJson->email, ['ahmadnurbrasta@gmail.com', 'irvansav28@gmail.com', 'ahmadnurbrasta@outlook.com'])) {
                                        $query = $query . 'and ticket_group = "' . preg_replace('/\\\\u([0-9a-fA-F]{4})/', '\\\\\\\\u$1', $subject) . '" ';
                                    } else {
                                        $query = $query . "and ticket_group = '" . mysqli_real_escape_string($mysqli, $subject_select ?? $subject) . "' ";
                                    }
                                } //if
                            } elseif (
                                $chatType == "IGCOMMENT" ||
                                $chatType == "FBCOMMENT" ||
                                $chatType == "TWITTERCOMMENT" ||
                                $chatType == "YTCOMMENT" ||
                                $chatType == "GOOGLEPLAY" ||
                                $chatType == "GOOGLEBUSINESS" ||
                                $chatType == "SHOPEE" ||
                                $chatType == "TOKOPEDIA"
                            ) {
                                //khusus comment, ticket dibedakan dari post id
                                $commentJson = json_decode($chatMessage);
                                // echo $chatMessage."<BR>";

                                $postId = $commentJson->postId;

                                if (!empty($postId)) {
                                    //tambahkan group berdasar post id
                                    $query = "SELECT ticket_status, chat_log_id_start, chat_log_id_end FROM botika_helpdesk_tickets WHERE " .
                                        "bot_id = '" . $botId . "' and ticket_status != 'pending'  and ticket_status != 'solved' ";

                                    $query = $query . 'AND ticket_group = "' . mysqli_real_escape_string($mysqli, $postId) . '" ' .

                                    //per 19 maret 2021, tambah reqeust dari andri
                                    $query = $query . "AND user_id = '" . mysqli_real_escape_string($mysqli, $userId) . "' ";
                                } //if
                            } //if

                            $query = $query . "order by ticket_idx desc limit 1";
                            $result = mysqli_query($mysqli, $query);
                            $db_field = mysqli_fetch_assoc($result);

                            writeLog("[$uniqode - helpdeskticketcreate] [SALMA DEBUG $botId RESUBJECT GET 1] $query " . json_encode($db_field));

                            $checkTicketTicketId = $db_field["ticket_id"];
                            $checkTicketTicketStatus = strtolower($db_field["ticket_status"]);
                            $checkTicketTicketAssigneeId = $db_field["ticket_assignee_id"];
                            //if(($groupBySubject=="true") || ($groupBySubject===true) || ($groupBySubject=="1")) {
                            if ($groupBySubject) {
                                //do none, query utk email sdh group by subject
                            } else {
                                //per 28 des 18 semua email masuk jadi tiket baru
                                if ($chatType == "EMAIL" || $chatType == "EMAILOUTLOOK") {$checkTicketTicketStatus = "";}
                                // if( $chatType=="IGCOMMENT"  || $chatType=="FBCOMMENT" || $chatType=="TWITTERCOMMENT" ) { $ticketStatus = ""; }
                            } //if

                            # prioritize ticket from the queue
                            ## it is only work if current ticket sets wait param as true
                            ## check if there is ticket in queue, then prioritize this ticket
                            # prepare queue
                            $queue = null;

                            $totalSlot = 0;
                            if ($arrAgent) {
                                foreach ($arrAgent as $agentKey => $agentVal) {
                                    if ($agentVal['total'] < $ticketPerAgent) {
                                        $totalSlot += $ticketPerAgent - $agentVal['total'];
                                    }

                                }
                            }

                            writeLog("[$uniqode - helpdeskticketcreate] Total slot available for $chatType : $totalSlot");

                            # check ticket in queue
                            $query = "
								SELECT COUNT(bht.ticket_idx) as total FROM botika_helpdesk_tickets2 bht
								JOIN botika_chat_logs bcl ON bht.chat_log_id_start = bcl.chat_log_id
								WHERE bht.bot_id = '" . $botId . "'
								AND bcl.chat_type = '" . $chatType . "'
								AND bht.ticket_status = 'unassigned'
							";
                            $result = mysqli_query($mysqli, $query);
                            $queue = mysqli_fetch_assoc($result);

                            if (
                                ($checkTicketTicketStatus == "") ||
                                ($checkTicketTicketStatus == "pending") ||
                                ($checkTicketTicketStatus == "solved") ||
                                ($checkTicketTicketStatus == "closed")
                            ) {
                                // create ticket
                                if (
                                    (!$queue || ($queue && $queue['total'] <= $totalSlot)) && !$waitOk && (sizeof($arrAgent) > 0)
                                ) {
                                    $found = false;
                                    foreach ($arrAgent as $agentKey => $agentVal) {
                                        $accIdAgent = $agentVal['accountId'];

                                        // update jumlah total yg dihandle
                                        $query1 = "select count(ticket_assignee_id) AS total
													from botika_helpdesk_tickets2 bht
													join botika_chat_logs bcl ON bht.chat_log_id_start = bcl.chat_log_id
													where ticket_status IN ('unsolved', 'hold') and bht.bot_id = '" . $botId . "'
													and bcl.chat_type = '" . $chatType . "'
													and bht.ticket_assignee_id = '$accIdAgent'";
                                        $result1 = mysqli_query($mysqli, $query1);
                                        $total1 = 0;
                                        while ($db_field1 = mysqli_fetch_assoc($result1)) {
                                            $arrAgent[$agentKey]["total"] = (int) $db_field1["total"];
                                        }

                                        if ($arrAgent[$agentKey]["total"] >= $ticketPerAgent) {
                                            writeLog("[$uniqode - helpdeskticketcreate] Agent: $accIdAgent limit reached, continue", "logDebug_" . date("Y-m-d_H"));
                                            continue;
                                        }

                                        $lockAgent = checkLock2("assign_$botId-$accIdAgent", 3, 1);
                                        writeLock("assign_$botId-$accIdAgent");
                                        if (!$lockAgent) {
                                            writeLog("[$uniqode - helpdeskticketcreate] Agent: $accIdAgent already assigned, continue", "logDebug_" . date("Y-m-d_H"));
                                            continue;
                                        }

                                        $found = true;
                                        writeLock("helpdeskticketcreate_$botId-$userId-$chatType-$ticketGroupVal");
                                        $creationDate = date("Y-n-j H:i:s");
                                        if ($chatType === "PHONE") {
                                            $query = "insert into botika_helpdesk_tickets(" .
                                            "ticket_id, " .
                                            "ticket_assignee_id, " .

                                            "ticket_status, chat_log_id_start, chat_log_idx_start, chat_id, " .
                                            "ticket_group, ticket_category, " .
                                            "user_id, bot_id, department_id, workspace_id, chat_type2, creation_date) values(" .

                                            "'" . $ticketId . "', " .
                                            "'" . $agentVal["accountId"] . "', " .

                                            "'unsolved', " .
                                            "'" . $chatLogIdStart . "', " .
                                            "'" . $chatLogIdxStart . "', " .
                                            "'" . $chatId . "', " .
                                            '"' . mysqli_real_escape_string($mysqli, $ticketGroupVal) . '", ' .
                                            "'" . mysqli_real_escape_string($mysqli, $ticketCategory) . "', " .
                                                "'" . $userId . "', '" . $botId . "', " .
                                                "'" . $departmentId . "', " .
                                                "'" . $workspaceId . "', " .
                                                "'" . $chatType . "', " .
                                                "'" . $creationDate . "')";

                                            // echo "3:".$query;
                                            // exit;
                                            $result = mysqli_query($mysqli, $query);

                                            $lastid = mysqli_insert_id($mysqli);
                                            writeLog("[$uniqode - helpdeskticketcreate] [SALMA DEBUG $botId INSERT 3] $query " . json_encode($lastid));

                                            $ticketIdx = $lastid;
                                            $ticketCreationDate = $creationDate;

                                            # per 12 Feb 24, ubah agar insert analytic & report omni dihandle oleh helpdesk
                                            $timestampNow = strtotime('now');
                                            //agent
                                            $queryAgent2 = "select ba.account_id, ba.username, ba.name from botika_accounts ba where ba.account_id  = '$accIdAgent'";
                                            $resultAgent2 = mysqli_query($mysqli, $queryAgent2);
                                            $agentData2 = $resultAgent2->fetch_all(MYSQLI_ASSOC);

                                            $agent = $accIdAgent;
                                            $agentName = $agentData2[0]['name'];

                                            $contentReport = [
                                                'date' => date("Y-m-d H:i:s", strtotime($timestampNow)),
                                                'ticket_id' => $ticketId,
                                                'to_id' => $accIdAgent,
                                                'to_name' => $agentName,
                                                'ticket_status' => 'unsolved',
                                            ];

                                            $key = true;
                                            while ($key) {
                                                $analyticId = randString();
                                                $analyticQuery = "select analytic_id from botika_analytics where analytic_id = '$analyticId'";
                                                $checkAnalyticId = mysqli_query($mysqli, $analyticQuery);
                                                if (mysqli_num_rows($checkAnalyticId) < 1) {
                                                    $key = false;
                                                }
                                            }

                                            $insertAnalytic = "insert into botika_analytics (analytic_id, analytic_group, analytic_key, analytic_value, analytic_date, bot_id, account_id, analytic_value_new)
												values ('$analyticId', 'Start AART', '$ticketId', '$timestampNow', '" . date('Y-m-d H:00:00', $timestampNow) . "', '$botId', '$accIdAgent', '$timestampNow')";
                                            $result = mysqli_query($mysqli, $insertAnalytic);
                                            $lastid = mysqli_insert_id($mysqli);

                                            $insertNewAnalytic = "INSERT INTO botika_omny_analytic_details (ticket_idx, `type`, `start`, `creation_date`, `bot_id`, `ticket_creation_date`) VALUES ('$ticketIdx', 'Agent Response Time', '$timestampNow', '" . date('Y-m-d H:00:00') . "', '$botId', '$ticketCreationDate')";
                                            $resultNew = mysqli_query($mysqli, $insertNewAnalytic);
                                            writeLog('insert new analytic :' . $insertNewAnalytic . ' result : ' . $resultNew, 'LogAnalytic');
                                            //kalau tidak ada, maka buat random
                                            //create random string
                                            $milliseconds = round(microtime(true) * 1000);
                                            $reportId = $milliseconds;
                                            $reportId = $reportId . $botId;
                                            $reportId = substr(md5($reportId), 0, 10);

                                            $insertReport = "insert into botika_reports (report_id, report_group, report_title, report_content, bot_id, account_id, report_date, creation_date)
												values ('$reportId', 'Ticket Routing', '$ticketId', '" . json_encode($contentReport) . "', '$botId', '$accIdAgent', '" . date('Y-m-d 00:00:00', $timestampNow) . "', '" . date('Y-m-d H:i:s', $timestampNow) . "')";
                                            $result = mysqli_query($mysqli, $insertReport);
                                            $lastid = mysqli_insert_id($mysqli);

                                            //---------------------------------------------------------
                                            $sendArr["assigneeId"] = $accIdAgent;
                                            //---------------------------------------------------------
                                            // cari extension
                                            $query = "select value from botika_omny_multi_level_values " .
                                                "where agent_id = '$accIdAgent' and type = 'extensions'";
                                            $result = mysqli_query($mysqli, $query);

                                            if (mysqli_num_rows($result) == 0) {
                                                //do none, tidak ada extension
                                            } else {

                                                // {"impi":"7001","impu":"sip:7001@sip.botika.online","displayName":"Botika"}
                                                $db_field = mysqli_fetch_assoc($result);

                                                $extension = json_decode($db_field["value"], true);

                                                $sendArr["command"] =
                                                array(
                                                    array(
                                                        "category" => "call",
                                                        "payload" => array(
                                                            "type" => "route",
                                                            "action" => "transfer",
                                                            "value" => $extension["impi"],
                                                        ),
                                                    ),
                                                );

                                                writeLog("[$uniqode - helpdeskticketcreate] Transfer extension on helpdeskticketcreate " . json_encode($sendArr) . " at : " . date("Y-m-d H:i:s"), "logDebug_" . date("Y-m-d_H"));
                                            } //if
                                        } else {
                                            //assign ke agent yang paling sedikit

                                            // if ($botId == 'bqjpythfut1' && $userId == 'tester 33') {
                                                $query = "insert into botika_helpdesk_tickets (ticket_id, ticket_status, chat_log_id_start, chat_log_idx_start, chat_id, ticket_group, ticket_category, user_id, bot_id, department_id, workspace_id, creation_date, chat_type2)" .
                                                " values ('$ticketId', 'unassigned', '$chatLogIdStart', '$chatLogIdxStart',  '$chatId', " .
                                                '"' . $ticketGroupVal . '", ' .
                                                "'" . mysqli_real_escape_string($mysqli, $ticketCategory) . "', " .
                                                    "'$userId', '$botId', '$departmentId', '$workspaceId', '$creationDate', '$chatType')";
                                            // } else {
                                            //     $query = "insert into botika_helpdesk_tickets (ticket_id, ticket_status, chat_log_id_start, chat_log_idx_start, chat_id, ticket_group, ticket_category, user_id, bot_id, department_id, workspace_id, creation_date, chat_type2)" .
                                            //     " values ('$ticketId', 'unassigned', '$chatLogIdStart', '$chatLogIdxStart',  '$chatId', " .
                                            //     "'" . mysqli_real_escape_string($mysqli, $ticketGroupVal) . "', " .
                                            //     "'" . mysqli_real_escape_string($mysqli, $ticketCategory) . "', " .
                                            //         "'$userId', '$botId', '$departmentId', '$workspaceId', '$creationDate', '$chatType')";
                                            // }

                                            // echo "3:".$query;
                                            // exit;
                                            $result = mysqli_query($mysqli, $query);
                                            $lastid = mysqli_insert_id($mysqli);
                                            writeLog("[$uniqode - helpdeskticketcreate] [SALMA DEBUG $botId INSERT 4] $query " . json_encode($lastid));

                                            $ticketIdx = $lastid;
                                            $ticketCreationDate = $creationDate;
                                        }

                                        $sendArr["result"] = "success";
                                        $sendArr["ticketId"] = $ticketId;
                                        //---------------------------------------------------------
                                        // echo '{"result":"success", "ticketid":"'.$ticketId.'"}';
                                        echo json_encode($sendArr);
                                        writeLog("[$uniqode - helpdeskticketcreate] Returning " . json_encode($sendArr), "logDebug_" . date("Y-m-d_H"));
                                        break;
                                    }

                                    if (!$found) {
                                        $send_message = false;

                                        $output = json_encode(['result' => 'error', 'msg' => 'Agent full']);
                                        echo $output;

                                        // echo '{"result":"error","msg":"Agent full"}';
                                        writeLog("[$uniqode - helpdeskticketcreate] Returning " . $output, "logDebug_" . date("Y-m-d_H"));
                                    }

                                } else {
                                    //bila tidak, maka semua agent sdh penuh
                                    // atau memiliki queue

                                    //if(($waitOk===true) || ($waitOk=="true") || ($waitOk==="1")) {
                                    if ($waitOk) {

                                        // echo "wait=true";
                                        // exit;

                                        writeLock("helpdeskticketcreate_$botId-$userId-$chatType-$ticketGroupVal");
                                        //masukkan ke unassigned
                                        // if ($emailJson->email == 'ahmadnurbrasta@gmail.com') {
                                            $query = "insert into botika_helpdesk_tickets(ticket_id, ticket_status, chat_log_id_start, chat_log_idx_start, chat_id, ticket_group, ticket_category, " .
                                            "user_id, bot_id, department_id, workspace_id, chat_type2, creation_date) values(" .
                                            "'" . $ticketId . "', " .
                                            "'unassigned', " .
                                            "'" . $chatLogIdStart . "', " .
                                            "'" . $chatLogIdxStart . "', " .
                                            "'" . $chatId . "', " .
                                            '"' . $ticketGroupVal . '", ' .
                                            "'" . mysqli_real_escape_string($mysqli, $ticketCategory) . "', " .
                                            "'" . $userId . "', '" . $botId . "', " .
                                            "'" . $departmentId . "', " .
                                            "'" . $workspaceId . "', " .
                                            "'" . $chatType . "', " .
                                            "'" . date("Y-n-j H:i:s") . "')";
                                        // } else {
                                        //     $query = "insert into botika_helpdesk_tickets(ticket_id, ticket_status, chat_log_id_start, chat_log_idx_start, chat_id, ticket_group, ticket_category, " .
                                        //     "user_id, bot_id, department_id, workspace_id, chat_type2, creation_date) values(" .
                                        //     "'" . $ticketId . "', " .
                                        //     "'unassigned', " .
                                        //     "'" . $chatLogIdStart . "', " .
                                        //     "'" . $chatLogIdxStart . "', " .
                                        //     "'" . $chatId . "', " .
                                        //     "'" . mysqli_real_escape_string($mysqli, $ticketGroupVal) . "', " .
                                        //     "'" . mysqli_real_escape_string($mysqli, $ticketCategory) . "', " .
                                        //     "'" . $userId . "', '" . $botId . "', " .
                                        //     "'" . $departmentId . "', " .
                                        //     "'" . $workspaceId . "', " .
                                        //     "'" . $chatType . "', " .
                                        //     "'" . date("Y-n-j H:i:s") . "')";
                                        // }

                                        // echo "4:".$query;
                                        // exit;
                                        $result = mysqli_query($mysqli, $query);

                                        $lastid = mysqli_insert_id($mysqli);
                                        writeLog("[$uniqode - helpdeskticketcreate] [SALMA DEBUG $botId INSERT 5] $query " . json_encode($lastid));

                                        //buat nomor tunggu
                                        $query = "select count(ticket_idx) as total from botika_helpdesk_tickets " .
                                            "where ticket_status = 'unassigned' and " .
                                            "bot_id = '" . $botId . "' ";

                                        //echo $query;
                                        $result = mysqli_query($mysqli, $query);

                                        if (mysqli_num_rows($result) == 0) {
                                            $waitNum = 0;
                                        } else {
                                            $db_field = mysqli_fetch_assoc($result);
                                            $waitNum = $db_field["total"];
                                        } //if

                                        $tempArr["result"] = "success";
                                        $tempArr["ticketId"] = $ticketId;
                                        $tempArr["wait"] = $waitNum;

                                        //-----------------------------
                                        if (
                                            ($chatType == "WEBHOOK") ||
                                            ($chatType == "PHONE")
                                        ) {
                                            $tempArr["command"] =
                                            array(
                                                array(
                                                    "category" => "call",
                                                    "payload" => array(
                                                        "type" => "control",
                                                        "action" => "helpdesk",
                                                        "value" => "wait",
                                                    ),
                                                ),
                                            );

                                            writeLog("[$uniqode - helpdeskticketcreate] Transfer extension on helpdeskticketcreate : wait " . json_encode($tempArr) . " at : " . date("Y-m-d H:i:s"), "logDebug_" . date("Y-m-d_H"));
                                        } //if

                                        //-----------------------------
                                        // echo '{"result":"success","wait":'.$waitNum.'}';
                                        echo json_encode($tempArr);
                                        writeLog("[$uniqode - helpdeskticketcreate] Returning " . json_encode($tempArr), "logDebug_" . date("Y-m-d_H"));

                                    } else {
                                        # if queue exist, then prioritize queue ticket
                                        $arrAssign = [];

                                        # per 5 feb 24, matikan assign ketika create ticket
                                        /*
                                        # per 8 Sept 23, tambah lock agar menghindari keassign berulang
                                        $lockAssign = checkLock3("assign_$botId");

                                        if(!$lockAssign) {
                                        writeLock("assign_$botId");
                                        # per 1 Feb 24, tambahkan pengecekan agent
                                        foreach($arrAgent as $kagent => $vagent) {
                                        // 1 Feb 2024, tambahkan pengecekan status agent
                                        $queryCheck = "SELECT account_id, login, login_status, last_active_date FROM botika_accounts WHERE account_id = '".$arrAgent[0]["accountId"]."'";
                                        $queryAgent = mysqli_query($mysqli, $queryCheck);
                                        $checkAgent = mysqli_fetch_assoc($queryAgent);
                                        // jika tidak ada data, shift agent dan lanjut query
                                        if(
                                        !$checkAgent ||
                                        ($checkAgent && ($checkAgent['login'] != 1 || ($checkAgent['login'] == 1 && ($checkAgent['last_active_date'] <= date("Y-m-d H:i:s", strtotime("-30 minutes")) || strtolower($checkAgent['login_status']) != 'available'))))
                                        ) {
                                        writeLog("[$uniqode - helpdeskticketcreate] Agent not available, skip assign agent : ".json_encode($checkAgent), "logDebug_".date("Y-m-d_H"));
                                        continue;
                                        }

                                        // per 24 Januari 23 ubah pengecekan dengan query mengantisipasi agent sudah melebihi routing
                                        // update jumlah total yg dihandle
                                        $query1 = "SELECT ticket_assignee_id, botika_chat_logs.chat_type, COUNT(ticket_assignee_id) AS total, botika_helpdesk_tickets.creation_date ".
                                        "FROM botika_helpdesk_tickets ".
                                        "JOIN botika_chat_logs ON botika_helpdesk_tickets.chat_log_id_start = botika_chat_logs.chat_log_id ".
                                        "where ticket_status IN ('unsolved', 'hold') and botika_helpdesk_tickets.bot_id = '".$botId."' ".
                                        "and ticket_assignee_id = '".$vagent["accountId"]."' ".
                                        "GROUP BY ticket_assignee_id, botika_chat_logs.chat_type";
                                        $result1 = mysqli_query($mysqli, $query1);
                                        $total1 = 0;
                                        while($db_field1 = mysqli_fetch_assoc($result1)){
                                        # 29 maret 2023, jika ticket per chat type sama dengan limit, set jadi 9999
                                        if($db_field1['chat_type'] >= $ticketPerAgent){
                                        $arrAgent[$kagent]["total"] = 9999;
                                        }elseif($db_field1['chat_type'] == $chatType) {
                                        $arrAgent[$kagent]["total"] = $db_field1["total"];
                                        }
                                        $total1 += $db_field1["total"];
                                        }
                                        if($arrAgent[$kagent]["totalAllChannel"]!="") {
                                        $arrAgent[$kagent]["totalAllChannel"] = $total1;
                                        } //if

                                        $query = "SELECT bht.ticket_id, bht.bot_id, bht.user_id
                                        FROM botika_helpdesk_tickets bht
                                        JOIN botika_chat_logs bcl ON bht.chat_log_id_start = bcl.chat_log_id
                                        WHERE bht.bot_id = '".$botId."'
                                        AND bcl.chat_type = '$chatType'
                                        AND bht.ticket_status = 'unassigned'
                                        LIMIT 1";
                                        $result = mysqli_query($mysqli, $query);
                                        $queue = mysqli_fetch_assoc($result);

                                        if (
                                        isset($queue['ticket_id']) &&
                                        isset($queue['bot_id']) &&
                                        (sizeof($arrAgent)>0) &&
                                        ($arrAgent[$kagent]["total"]<$ticketPerAgent)
                                        ) {
                                        $lockAssign = checkLock2("assign_$botId-$ticketId", 300, 1);
                                        writeLock("assign_$botId-$ticketId");
                                        if(!$lockAssign) {
                                        writeLog("[$uniqode - helpdeskticketcreate] Ticket: ".$queue['ticket_id']." already assigned, continue", "logDebug_".date("Y-m-d_H"));
                                        } else {
                                        # per 24 Jan 23, tambah lock agar ulang menghindari kelebihan routing
                                        $check_priority_ticket = get_content('https://api.botika.online/private/helpdesk/index.php', json_encode([
                                        "accessToken" => "b125de21-90f5-457b-913e-fdfda40b17df",
                                        'action' => 'helpdeskticketupdate',
                                        'assignto' => $vagent["accountId"],
                                        'ticketstatus' => 'unsolved',
                                        'ticketid' => $queue['ticket_id'],
                                        'botid' => $queue['bot_id'],
                                        ]));

                                        $arrAssign[] = array(
                                        "ticketId" => $queue['ticket_id'],
                                        "userId" => $queue['user_id'],
                                        "accountId" => $vagent["accountId"]
                                        );

                                        writeLog("[$uniqode - helpdeskticketcreate] Assigned from queue ".$queue['ticket_id']." to ".$arrAgent[0]['accountId'], "logDebug_".date("Y-m-d_H"));
                                        }

                                        }
                                        }
                                        deleteLock("assign_$botId");
                                        }
                                         */

                                        $send_message = false;

                                        $tempArr["result"] = "error";
                                        $tempArr["msg"] = 'Agent full';
                                        // $tempArr["assigned"] = $arrAssign;

                                        $output = json_encode($tempArr);
                                        echo $output;

                                        // echo '{"result":"error","msg":"Agent full"}';
                                        writeLog("[$uniqode - helpdeskticketcreate] Returning " . $output, "logDebug_" . date("Y-m-d_H"));

                                    } //if
                                } //if($ticketPerAgent<$arrAgent[0]["total"]) {

                            } else {
                                echo '{"result":"error","msg":"Ticket still open","ticketId":"' . $checkTicketTicketId . '","ticketstatus":"' . $checkTicketTicketStatus . '","accountId":"' . $checkTicketTicketAssigneeId . '"}';
                                writeLog("[$uniqode - helpdeskticketcreate] Returning " . '{"result":"error","msg":"Ticket still open","ticketId":"' . $checkTicketTicketId . '","ticketstatus":"' . $checkTicketTicketStatus . '","accountId":"' . $checkTicketTicketAssigneeId . '"}', "logDebug_" . date("Y-m-d_H"));
                            }

                        } //if($agentTransfer=="manual") {
                    } //if($accountId!="") {

                    //         socket-send if $send_message true
                    if ($send_message === true) {
                        if ($chatAccess == 'all') {
                            //sendTo
                            $queryAcc = "select ba.access_token from botika_bots bb, botika_accounts ba ". 
                                        "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and login = '1' and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32 ".
                                        "union ".
                                        "select ba.access_token from botika_bot_access bb, botika_accounts ba ".
                                        "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and login = '1' and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32";
                            $resultAcc = mysqli_query($mysqli, $queryAcc);
                            $sendSocketAccId = $resultAcc->fetch_all(MYSQLI_NUM);
                        } else {
                            //sendTo
                            $queryAcc = "select ba.access_token from botika_bots bb, botika_accounts ba ".
                                        "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and login = '1' and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32 ".
                                        "union ".
                                        "select ba.access_token from botika_bot_access bb, botika_accounts ba ". 
                                        "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and ba.account_id = '$agent' and (bb.level = 'agent' or bb.level IS NULL) ".
                                        "union ".
                                        "select ba.access_token from botika_bot_access bb, botika_accounts ba ". 
                                        "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and bb.level = 'supervisor' and login = '1' and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32";
                            $resultAcc = mysqli_query($mysqli, $queryAcc);
                            $sendSocketAccId = $resultAcc->fetch_all(MYSQLI_NUM);
                        }

                        // ambil 2 query dari last id + 1, [1] untuk tiket number dan [0] untuk tiket group
                        // $whereId = intval($lastid)+1;
                        // $queryLastid = 'SELECT * FROM botika_helpdesk_tickets
                        // WHERE bot_id = "'.$botId.'"
                        // AND ticket_idx < "'.$whereId.'"
                        // ORDER BY ticket_idx DESC
                        // LIMIT 2';

                        // if ($botId == 'bcsx3oz7ysx') {
                        //     $mysqli->begin_transaction();
                        //     try {
                        //         // ambil total tiket yg ada
                        //         $queryLastid = 'SELECT ticket_number FROM botika_helpdesk_tickets WHERE bot_id = "'.$botId.'" AND ticket_number IS NOT NULL ORDER BY ticket_idx desc limit 1 FOR UPDATE';
                        //         $resultLastid = mysqli_query($mysqli, $queryLastid);
                        //         $lastIdData = mysqli_fetch_assoc($resultLastid);
                        //         $ticketNumber = 1;

                        //         if (isset($lastIdData['ticket_number']) && !empty($lastIdData['ticket_number']) && intval($lastIdData['ticket_number']) > 0) {
                        //             $last_ticket = intval($lastIdData['ticket_number']);
                        //             $ticketNumber = $last_ticket + 1;
                        //         }

                        //         writeLog("[$uniqode - TRANSACTION FOR TICKET NUMBER] lastIdData: ". json_encode($lastIdData) . " ticketNumber: $ticketNumber");
    
                        //         $query = "update botika_helpdesk_tickets set " .
                        //         "ticket_number = '" . $ticketNumber . "' " .
                        //         "where ticket_idx = '" . $lastid . "' ";
    
                        //         $result = mysqli_query($mysqli, $query);
    
                        //         $mysqli->commit();
                        //     } catch (mysqli_sql_exception $exception) {
                        //         $mysqli->rollback();
                        //         writeLog("[$uniqode - helpdeskticketcreate] Rollback transaction: " . $exception->getMessage(), "logDebug_" . date("Y-m-d_H"));
                        //     }

                        // } else {

                            // ambil total tiket yg ada
                            $queryLastid = 'SELECT ticket_number FROM botika_helpdesk_tickets WHERE bot_id = "'.$botId.'" AND ticket_number IS NOT NULL ORDER BY ticket_idx desc limit 1';
                            $resultLastid = mysqli_query($mysqli, $queryLastid);
                            $lastIdData = mysqli_fetch_assoc($resultLastid);

                            //set tiket number
                            $ticketNumber = 1;
                            
                            $no = 0;
                            while (true) {
                                if ($ticketNumber > 1) {
                                    $ticketNumber++;
                                } else if (isset($lastIdData['ticket_number']) && !empty($lastIdData['ticket_number']) && intval($lastIdData['ticket_number']) > 0) {
                                    $last_ticket = intval($lastIdData['ticket_number']);
                                    $ticketNumber = $last_ticket + 1;
                                }

                                writeLog("[$uniqode - TICKET NUMBER] $no lastIdData: ". json_encode($lastIdData) . " ticketNumber: $ticketNumber");

                                $lockTicketNumber = checkLock3("assign_$botId-$ticketNumber");
                                writeLock("assign_$botId-$ticketNumber");
                                if (!$lockTicketNumber) {
                                    $no++;
                                    writeLog("[$uniqode - helpdeskticketcreate] Botid: $botId ticketNumber: $ticketNumber already used, continue", "logDebug_" . date("Y-m-d_H"));
                                    continue;
                                }

                                break;
                            }

                            $query = "update botika_helpdesk_tickets set " .
                                "ticket_number = '" . $ticketNumber . "' " .
                                "where ticket_id = '" . $ticketId . "' ";

                            writeLog("[$uniqode - helpdeskticketcreate] Update ticketNumber: $ticketNumber , ticketId: $ticketId", "logDebug_" . date("Y-m-d_H"));
        
                            $result = mysqli_query($mysqli, $query);
                        // }

                        if (!empty($user)) {
                            $socketData["user"] = $user[0];
                        } else {
                            $socketData["user"]["userId"] = $userId;
                        } //if

                        if (empty($agent)) {
                            $socketData["ticketStatus"] = "unassigned";
                            # 2022-05-19
                            # send to
                            $resultAcc = mysqli_query($mysqli, "
								SELECT ba.access_token
								FROM botika_bots bb, botika_accounts ba
								WHERE ba.account_id = bb.account_id and bb.bot_id = '" . $botId . "'
								UNION
								SELECT ba.access_token
								FROM botika_bot_access bb, botika_accounts ba
								WHERE ba.account_id = bb.account_id and bb.bot_id = '" . $botId . "'
							");
                            $sendSocketAccId = $resultAcc->fetch_all(MYSQLI_NUM);
                        } else {
                            $socketData["ticketStatus"] = "unsolved";
                            # 2022-05-19
                            # send to
                            $resultAcc = mysqli_query($mysqli, "
								SELECT ba.access_token
									FROM botika_bots bb, botika_accounts ba
									WHERE ba.account_id = bb.account_id and bb.bot_id = '" . $botId . "'
								UNION
								SELECT ba.access_token
									FROM botika_bot_access bb, botika_accounts ba
									WHERE ba.account_id = bb.account_id and bb.bot_id = '" . $botId . "'
									AND ba.account_id = '" . $agent . "'
									AND (bb.level = 'agent' OR bb.level IS NULL)
								UNION
								SELECT ba.access_token from botika_bot_access bb, botika_accounts ba
									WHERE ba.account_id = bb.account_id and bb.bot_id = '" . $botId . "'
									AND bb.level = 'supervisor'
							");
                            $sendSocketAccId = $resultAcc->fetch_all(MYSQLI_NUM);
                        } //if

                        $socketData["contentType"] = "ticket"; //chat or ticket
                        $socketData["action"] = "create"; //chat or ticket
                        $socketData["botId"] = $botId;
                        $socketData["ticketId"] = $ticketId;
                        $socketData["ticketNumber"] = $ticketNumber;
                        $socketData["subject"] = $lastIdData[0]['ticket_group'] ?? null;
                        $socketData["messengerType"] = $chatType;
                        $socketData["agent"] = $agent;
                        $socketData["agentName"] = $agentName;
                        $socketData["department"] = $departmentId;
                        $socketData["sendTo"] = $sendSocketAccId;
                        $socketData["identifier"] = $uniqode;

                        $jsonDataSocket = json_encode($socketData);
                        // writeLog($jsonDataSocket);

                        // $url = 'https://socket.botika.online/send/request';
                        // $url = 'http://socket.botika.online:3000/send/request';
                        // $token = '10b011f0-0b0c-4ecb-98d0-667057667ad9';

                        //kirim json ke socket
                        // $socket = getContentAsyncToken($url, $jsonDataSocket, $token );
                        // $socket = guzzleRequest($url, $jsonDataSocket, ["Token" => $token]);
                        // $header = '{"Token":"'.$token.'"}';
                        // $command = "/usr/bin/php ".realpath('.')."/guzzle_async.php";
                        // $command = $command." '".$url."' '".$jsonDataSocket."' '".$header."'";
                        // $command = $command." >> /dev/null &";
                        // $socket = shell_exec($command);

                        // Trigger socket
                        socket()->trigger('bot-' . $botId, 'ticket:create', $socketData);

                        // if($ticketId!="") {

                        //     //track sla
                        //     //-----------------------------------
                        //     //-----------------------------------
                        //     unset($tempArr);
                        //     $tempArr["accessToken"] = "9752a8aa-f741-45ec-9b1b-1c852ed697d6";
                        //     $tempArr["action"] = "trackvalue";
                        //     $tempArr["botid"] = $botId;

                        //     //group berdasar agent tsb
                        //     $tempArr["group"] = "SLA Response";

                        //     //track start tiap email
                        //     $tempArr["key"] = "Start ".$ticketId;
                        //     $tempArr["value"] = strtotime(date("Y-n-j H:i:s"));
                        //     $tempArr["date"] = date("Y-n-j H:i:s");

                        //     //writeLog(print_r($tempArr, true), "logRespondTime");
                        //     $jsonData = json_encode($tempArr);
                        //     //writeLog($jsonData, "logRespondTime");
                        //     //-----------------------------------
                        //     $url = 'http://api-env.qqgqwymb3m.ap-southeast-1.elasticbeanstalk.com/analytic/index.php';
                        //     $result = getContentAsync($url, $jsonData );
                        //     //-----------------------------------
                        //     //-----------------------------------

                        // } //if
                    } //if
                }

            } else {
                echo '{"result":"error","msg":"Ticket still open","ticketId":"' . $ticketId . '","ticketstatus":"' . $ticketStatus . '","accountId":"' . $ticketAssigneeId . '"}';
                writeLog("[$uniqode - helpdeskticketcreate] Returning " . '{"result":"error","msg":"Ticket still open","ticketId":"' . $ticketId . '","ticketstatus":"' . $ticketStatus . '","accountId":"' . $ticketAssigneeId . '"}', "logDebug_" . date("Y-m-d_H"));
            } //if
        } else {
            echo '{"result":"error","msg":"Empty chatId"}';
            writeLog("[$uniqode - helpdeskticketcreate] Returning Empty chatId", "logDebug_" . date("Y-m-d_H"));
        } //if

        deleteLock("helpdeskticketcreate_$botId-$userId-$chatType-$ticketGroupVal");
        // echo "check done<BR>";
    } else if ($json["action"] == "helpdeskticketget") {

        require_once "inc-db.php";

        $botId = sanitize_paranoid_string($json["botid"]);
        $userId = sanitize_paranoid_string($json["userid"]);
        $group = sanitize_paranoid_string($json["group"]);

        if (($botId == "") || ($userId == "")) {
            echo '{"result":"error","msg":"Empty botId or userId"}';
        } else {
            $query = "select * from botika_helpdesk_tickets where " .
                "bot_id = '" . $botId . "' and user_id = '" . $userId . "' ";

            if ($group != "") {
                $query = $query . 'and ticket_group = "' . $group . '" ';
            } //if

            $query = $query . "order by ticket_idx desc limit 0,1";

            $result = mysqli_query($mysqli, $query);

            if (mysqli_num_rows($result) == 0) {

                echo '{"result":"success",' .
                    '"ticketId":"",' .
                    '"ticketstatus":""}';

            } else {

                $db_field = mysqli_fetch_assoc($result);

                //per 28 des 18 semua email masuk jadi tiket baru
                $chatId = $db_field["chat_id"];

                $query1 = "select chat_type from botika_chat_logs where chat_id = '" . $chatId . "' ";
                $result1 = mysqli_query($mysqli, $query1);
                $db_field1 = mysqli_fetch_assoc($result1);

                $chatType = $db_field1["chat_type"];

                if ($chatType == "EMAIL" || $chatType == "EMAILOUTLOOK") {
                    $ticketId = "";
                    $ticketStatus = "";
                } else {
                    $ticketId = $db_field["ticket_id"];
                    $ticketStatus = $db_field["ticket_status"];
                } //if

                $agentId = $db_field["ticket_assignee_id"];
                if ($agentId == null) {$agentId = "";}
                //--------------------------------------------------
                $sendArr["result"] = "success";
                $sendArr["ticketId"] = $ticketId;
                $sendArr["accountId"] = $agentId;
                $sendArr["ticketstatus"] = $ticketStatus;
                //--------------------------------------------------
                if (
                    ($ticketStatus == "solved") || ($ticketStatus == "closed")
                ) {
                    //do none, tidak usah kirim command transfer
                } else if (
                    ($chatType == "WEBHOOK") ||
                    ($chatType == "PHONE")
                ) {

                    // cari extension
                    $query1 = "select value from botika_omny_multi_level_values " .
                        "where agent_id = '" . $agentId . "' and type = 'extensions'";
                    $result1 = mysqli_query($mysqli, $query1);

                    if (mysqli_num_rows($result1) == 0) {
                        //do none, tidak ada extension
                    } else {

                        // {"impi":"7001","impu":"sip:7001@sip.botika.online","displayName":"Botika"}
                        $db_field1 = mysqli_fetch_assoc($result1);

                        $extension = json_decode($db_field1["value"], true);

                        $sendArr["command"] =
                        array(
                            array(
                                "category" => "call",
                                "payload" => array(
                                    "type" => "route",
                                    "action" => "transfer",
                                    "value" => $extension["impi"],
                                ),
                            ),
                        );
                        writeLog("[$uniqode - helpdeskticketget] Transfer extension on helpdeskticketget " . json_encode($sendArr) . " at : " . date("Y-m-d H:i:s"), "logDebug_" . date("Y-m-d_H"));
                    } //if

                } //if
                //--------------------------------------------------
                echo json_encode($sendArr);

                // '{"result":"success",'.
                //     '"ticketid":"'.$ticketId.'",'.
                //     '"ticketstatus":"'.$ticketStatus.'"}';
            }
        } //if

    } else if ($json["action"] == "helpdeskticketupdate") {

        require_once "inc-db.php";

        if (isset($json["ticketid"])) {$ticketId = sanitize_paranoid_string($json["ticketid"]);}
        if (isset($json["botid"])) {$botId = sanitize_paranoid_string($json["botid"]);}

        //untuk meng assign ticket
        if (isset($json["assignto"])) {$assignTo = sanitize_paranoid_string($json["assignto"]);}

        if (isset($json["chatlogidstart"])) {$chatLogIdStart = sanitize_paranoid_string($json["chatlogidstart"]);}
        if (isset($json["chatlogidend"])) {$chatLogIdEnd = sanitize_paranoid_string($json["chatlogidend"]);}
        if (isset($json["chatlogidxend"])) {$chatLogIdxEnd = sanitize_paranoid_string($json["chatlogidxend"]);}
        if (isset($json["chatid"])) {$chatId = sanitize_paranoid_string($json["chatid"]);}
        if (isset($json["ticketstatus"])) {$ticketStatus = sanitize_paranoid_string($json["ticketstatus"]);}
        if (isset($json["ticketstatusomni"])) {$ticketStatusOmni = sanitize_paranoid_string($json["ticketstatusomni"]);}
        if (isset($json["additionalinfo"])) {$additionalInfo = sanitizeJSON($json["additionalinfo"]);}
        if (isset($json["ticketgroup"])) {$ticketGroup = sanitize_paranoid_string($json["ticketgroup"]);}
        if (isset($json["ticketpriority"])) {$ticketPriority = sanitize_paranoid_string($json["ticketpriority"]);}
        if (isset($json["ticketcategory"])) {$ticketCategory = sanitize_paranoid_string($json["ticketcategory"]);}
        if (isset($json["ticketsubcategory"])) {$ticketSubCategory = sanitize_paranoid_string($json["ticketsubcategory"]);}
        if (isset($json["ticket_number"])) {$ticketNumber = sanitize_paranoid_string($json["ticket_number"]);}

        if (isset($json["departementid"])) {$departmentId = sanitize_paranoid_string($json["departementid"]);}
        if (isset($json["departmentid"])) {$departmentId = sanitize_paranoid_string($json["departmentid"]);}

        if (isset($json["departementidx"])) {$departmentIdx = sanitize_paranoid_string($json["departementidx"]);}
        if (isset($json["departmentidx"])) {$departmentIdx = sanitize_paranoid_string($json["departmentidx"]);}

        writeLog("[$uniqode - helpdeskticketupdate] Receiving request " . json_encode($json) . " at : " . date("Y-m-d H:i:s"), "logDebug_" . date("Y-m-d_H"));

        if ($departmentId != "") {
            // $query = "SELECT workspace_id FROM botika_omny_workspaces ".
            //     "LEFT JOIN botika_bots ON ".
            //     "botika_omny_workspaces.account_id = botika_bots.account_id ".
            //     "WHERE bot_id = '".$botId."' ";
            $query = "SELECT workspace_id FROM botika_omny_workspace_bots " .
                "WHERE bot_id = '" . $botId . "' ";
            $result = mysqli_query($mysqli, $query);
            $db_field = mysqli_fetch_assoc($result);
            $workspaceId = $db_field["workspace_id"];
        } //if

        if (($botId == "") || ($ticketId == "")) {
            echo '{"result":"error","msg":"Empty botId or ticketId"}';
        } else {
            //     socket data      //

            $queryTicket = 'select ticket_assignee_id, user_id, chat_log_id_start, ticket_status, ticket_group, ticket_category, ticket_subcategory,
                additional_information, ticket_priority, department_id, chat_type2
                from botika_helpdesk_tickets where ticket_id  = "' . $ticketId . '" ';
            $resultTicket = mysqli_query($mysqli, $queryTicket);
            $fetchTicket = $resultTicket->fetch_all(MYSQLI_ASSOC);
            $pastAgent = $fetchTicket[0]['ticket_assignee_id'];

            $querySetup = 'select agent_chat_access from botika_bot_settings where bot_id = "' . $botId . '"';
            $resultSetup = mysqli_query($mysqli, $querySetup);
            $fetchSetup = $resultSetup->fetch_all(MYSQLI_ASSOC);
            $chatAccess = $fetchSetup[0]['agent_chat_access'];

            //dipanggil dl agar agent dpt ditimpa yang baru
            if (isset($pastAgent) && !empty($pastAgent)) {
                //agent
                $queryAgent = 'select ba.account_id, ba.username, ba.access_token from botika_accounts ba where ba.account_id  = "' . $pastAgent . '" ';
                $resultAgent = mysqli_query($mysqli, $queryAgent);
                $agentData = $resultAgent->fetch_all(MYSQLI_ASSOC);

                $agent = $agentData[0]['account_id'];
                $agentName = $agentData[0]['username'];
                $pastAgentToken = $agentData[0]['access_token'];
            } //if

            if (isset($assignTo) && !empty($assignTo)) {
                //agent
                $queryAgent = 'select ba.account_id, ba.username from botika_accounts ba where ba.account_id  = "' . $assignTo . '" ';
                $resultAgent = mysqli_query($mysqli, $queryAgent);
                $agentData = $resultAgent->fetch_all(MYSQLI_ASSOC);

                $agent = $agentData[0]['account_id'];
                $agentName = $agentData[0]['username'];
            } //if

            if ($chatAccess == 'all') {
                //sendTo
                $queryAcc = "select ba.access_token from botika_bots bb, botika_accounts ba ". 
                            "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and login = '1' and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32 ".
                            "union ".
                            "select ba.access_token from botika_bot_access bb, botika_accounts ba ".
                            "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and login = '1' and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32";
                $resultAcc = mysqli_query($mysqli, $queryAcc);
                $sendSocketAccId = $resultAcc->fetch_all(MYSQLI_NUM);
            } else {
                //sendTo bila unassigned maka ahanya sebagai pastAgent saja
                if (isset($ticketStatus) && ($ticketStatus !== 'unassigned' && $ticketStatus !== 'solved')) {
                    $queryAcc = 'select ba.access_token from botika_bots bb, botika_accounts ba where ba.account_id = bb.account_id and bb.bot_id = "' . $botId . '" ' .
                    ' union ' .
                    'select ba.access_token from botika_bot_access bb, botika_accounts ba where ba.account_id = bb.account_id and bb.bot_id = "' . $botId . '" ' .
                    //' and ba.account_id = "'.$agent.'" and (bb.level = "agent" or bb.level IS NULL)'.
                    ' union ' .
                        'select ba.access_token from botika_bot_access bb, botika_accounts ba where ba.account_id = bb.account_id and bb.bot_id = "' . $botId . '" ' .
                        ' and bb.level = "supervisor" ';
                    //jika ada assign agent, kirim juga ke agentnya
                } else if (isset($agent) && $agent) {
                    $queryAcc = 'select ba.access_token from botika_bots bb, botika_accounts ba where ba.account_id = bb.account_id and bb.bot_id = "' . $botId . '" ' .
                        ' union ' .
                        'select ba.access_token from botika_bot_access bb, botika_accounts ba where ba.account_id = bb.account_id and bb.bot_id = "' . $botId . '" ' .
                        ' and ba.account_id = "' . $agent . '" and (bb.level = "agent" or bb.level IS NULL)' .
                        ' union ' .
                        'select ba.access_token from botika_bot_access bb, botika_accounts ba where ba.account_id = bb.account_id and bb.bot_id = "' . $botId . '" ' .
                        ' and bb.level = "supervisor" ';
                } else {
                    $queryAcc = 'select ba.access_token from botika_bots bb, botika_accounts ba where ba.account_id = bb.account_id and bb.bot_id = "' . $botId . '" ' .
                        ' union ' .
                        'select ba.access_token from botika_bot_access bb, botika_accounts ba where ba.account_id = bb.account_id and bb.bot_id = "' . $botId . '" ' .
                        ' and bb.level = "supervisor" ';
                }
                $resultAcc = mysqli_query($mysqli, $queryAcc);
                $sendSocketAccId = $resultAcc->fetch_all(MYSQLI_NUM);
            }

            $queryUser = 'select user_id userId, concat(first_name,\' \', last_name) name, profile_pic profPic from botika_users where user_id = "' . $fetchTicket[0]['user_id'] . '"';
            $resultUser = mysqli_query($mysqli, $queryUser);
            $user = $resultUser->fetch_all(MYSQLI_ASSOC);

            //messenger type
            // $queryType = 'select chat_type from botika_chat_log_users where user_id = "' . $fetchTicket[0]['user_id'] . '" and bot_id = "' . $botId . '" ORDER by chat_log_user_idx DESC LIMIT 0, 1';

            // // echo $queryType."<BR>";
            // // exit;

            // $resultType = mysqli_query($mysqli, $queryType);
            // $type = $resultType->fetch_all(MYSQLI_ASSOC);
            // $chatType = $type[0]['chat_type'];
            
            $chatType = $fetchTicket[0]['chat_type2'];

            //---------------------------------------------------------------------------------------------------

            //update tiket
            $updateCount = 0;
            $sqlAdd = "";

            if (isset($chatLogIdStart) && $chatLogIdStart != "") {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "chat_log_id_start = '" . $chatLogIdStart . "'";
                $updateCount++;
            } //if

            if (isset($chatLogIdEnd) && $chatLogIdEnd != '') {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "chat_log_id_end = '" . $chatLogIdEnd . "'";
                $updateCount++;
            } //if

            if (isset($chatLogIdxEnd) && $chatLogIdxEnd != '') {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "chat_log_idx_end = '" . $chatLogIdxEnd . "'";
                $updateCount++;
            } //if

            if (isset($chatId) && $chatId != '') {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "chat_id = '" . $chatId . "'";
                $updateCount++;
            } //if

            if (isset($assignTo)) {
                if ($assignTo != '') {
                    if ($updateCount > 0) {
                        $sqlAdd = $sqlAdd . ", ";
                    } //if

                    $sqlAdd = $sqlAdd . "ticket_assignee_id = '" . $assignTo . "'";
                    $updateCount++;
                } elseif ($assignTo == '' && isset($ticketStatus) && $ticketStatus == 'unassigned') {
                    if ($updateCount > 0) {
                        $sqlAdd = $sqlAdd . ", ";
                    } //if

                    $sqlAdd = $sqlAdd . "ticket_assignee_id = NULL";
                    $updateCount++;
                }
            } //if

            if (isset($ticketStatus) && $ticketStatus != '') {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "ticket_status = '" . $ticketStatus . "'";

                if ($ticketStatus == "solved") {
                    $sqlAdd = $sqlAdd . ", ticket_solved_date = '" . date("Y-n-j H:i:s") . "'";
                }

                $updateCount++;
            } //if

            if (isset($ticketStatusOmni)) {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if
                $sqlAdd = $sqlAdd . "ticket_status_omni = '" . $ticketStatusOmni . "'";
                $updateCount++;
            } //if

            if (isset($additionalInfo) && $additionalInfo != '' && strtolower($additionalInfo) != 'null') {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "additional_information = '" . $additionalInfo . "'";
                $updateCount++;
            } //if

            if (isset($ticketGroup)) {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . 'ticket_group = "' . $ticketGroup . '"';
                $updateCount++;
            } //if

            if (isset($ticketPriority) && $ticketPriority != '') {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "ticket_priority = '" . $ticketPriority . "'";
                $updateCount++;
            } //if

            if (isset($ticketCategory)) {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "ticket_category = '" . $ticketCategory . "'";
                $updateCount++;
            } //if

            if (isset($ticketSubCategory)) {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "ticket_subcategory = '" . $ticketSubCategory . "'";
                $updateCount++;
            } //if

            if (isset($departmentId) && $departmentId != '') {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "department_id = '" . $departmentId . "'";
                $updateCount++;
            } //if

            if (isset($departmentIdx) && $departmentIdx != '') {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "department_idx = '" . $departmentIdx . "'";
                $updateCount++;
            } //if

            if (isset($workspaceId) && $workspaceId != '') {
                if ($updateCount > 0) {
                    $sqlAdd = $sqlAdd . ", ";
                } //if

                $sqlAdd = $sqlAdd . "workspace_id = '" . $workspaceId . "'";
                $updateCount++;
            } //if

            if ($updateCount > 0) {
                $query = "update botika_helpdesk_tickets set " .
                    $sqlAdd . " " .
                    "where ticket_id = '" . $ticketId . "'";
                // writeLog($query);
                $result = mysqli_query($mysqli, $query);

            } //if($updateCount>0) {

// send to socket
            //------------------------------------------------------
            if (!empty($user)) {
                $socketData["user"] = $user[0];
            } else {
                $socketData["user"]["userId"] = $fetchTicket[0]['user_id'];
            } //if

            if (isset($ticketStatus)) {
                $socketData["ticketStatus"] = $ticketStatus;
            }

            if ((strtolower($ticketStatus) == "solved") ||
                (strtolower($ticketStatus) == "close") ||
                (strtolower($ticketStatus) == "closed") ||
                (strtolower($ticketStatus) == "pending")
            ) {
                $socketData["ticketStatus"] = strtolower($ticketStatus);
                $socketData["action"] = "close"; //chat or ticket
            } elseif (strtolower($ticketStatus) == "hold") {
                $socketData["ticketStatus"] = strtolower($ticketStatus);
                $socketData["action"] = "change"; //chat or ticket
            } else {
                if (isset($assignTo) && empty($assignTo)) {
                    $socketData["ticketStatus"] = "unassigned";
                } elseif (isset($assignTo) && !empty($assignTo)) {
                    $socketData["ticketStatus"] = "unsolved";
                } //if

                $socketData["action"] = "change"; //chat or ticket
            }

            if (isset($ticketStatusOmni)) {
                $socketData["ticketStatusOmni"] = $ticketStatusOmni;
            }
            $socketData["contentType"] = "ticket"; //chat or ticket
            $socketData["botId"] = $botId;
            $socketData["ticketId"] = $ticketId;
            $socketData["ticketNumber"] = $ticketNumber;
            $socketData["messengerType"] = $chatType;
            $socketData["agent"] = $agent;
            $socketData["agentName"] = $agentName;

            $socketData["info"] = isset($additionalInfo) ? $additionalInfo : $fetchTicket[0]['additional_information'];
            if (strtolower($socketData["info"]) == "null") {$socketData["info"] = "";}

            $socketData["priority"] = isset($ticketPriority) ? $ticketPriority : $fetchTicket[0]['ticket_priority'];
            $socketData["subject"] = isset($ticketGroup) ? $ticketGroup : $fetchTicket[0]['ticket_group'];
            $socketData["category"] = isset($ticketCategory) ? $ticketCategory : $fetchTicket[0]['ticket_category'];
            $socketData["subcategory"] = isset($ticketSubCategory) ? $ticketSubCategory : $fetchTicket[0]['ticket_subcategory'];
            $socketData["department"] = isset($departmentId) ? $departmentId : $fetchTicket[0]['department_id'];
            $socketData["sendTo"] = $sendSocketAccId;
            $socketData["identifier"] = $uniqode;

            if ((!empty($pastAgent) && (isset($assignTo) && !empty($assignTo) && $pastAgent !== $assignTo)) ||
                ((isset($ticketStatus) && strtolower($ticketStatus) == "solved") && (isset($assignTo) && !empty($assignTo) && $pastAgent != ""))) {
                $socketData["agentPast"] = $pastAgentToken;
            }

            $jsonDataSocket = json_encode($socketData);
// print_r($jsonDataSocket);

// $url = 'https://socket.botika.online/send/request';
            // $url = 'http://socket.botika.online:3000/send/request';
            // $token = '10b011f0-0b0c-4ecb-98d0-667057667ad9';
            // writeLog($jsonDataSocket);
            //kirim json ke socket
            // $socket = getContentAsyncToken($url, $jsonDataSocket, $token );
            // $socket = guzzleRequest($url, $jsonDataSocket, ["Token" => $token]);
            // $header = '{"Token":"'.$token.'"}';
            // $command = "/usr/bin/php ".realpath('.')."/guzzle_async.php";
            // $command = $command." '".$url."' '".$jsonDataSocket."' '".$header."'";
            // $command = $command." >> /dev/null &";
            // $socket = shell_exec($command);
            // Trigger socket
            socket()->trigger('bot-' . $botId, 'ticket:update', $socketData);

            echo '{"result":"success"}';

        } //if

    } else if ($json["action"] == "helpdeskticketassign") {

        // writeLog("[".date("Y-n-j H:i:s")."] check1", "logSpeed_".$rndStr);

        //untuk force assign ticket ke agen bila agent tsb baru saja login
        require_once "inc-db.php";

        if (isset($json["botid"])) {
            $botId = sanitize_paranoid_string($json["botid"]);
        } else {
            $botId = "";
        } //if

        if ($botId == "") {
            echo '{"result":"error","msg":"Empty botId or ticketId"}';
        } else {

            $lockAssign = checkLock3("assign_$botId");
            writeLock("assign_$botId");

            if (!$lockAssign) {
                echo '{"result":"success"}';
            } else {

                //-----------------------------------
                //-----------------------------------
                //jika distribusi tiketnya otomatis, maka bila solved,
                //maka auto distribute pendingan
                $query = "select * from botika_bot_settings where bot_id = '" . $botId . "' ";
                $result = mysqli_query($mysqli, $query);

                if (mysqli_num_rows($result) == 0) {

                    $agentTransfer = "manual";
                    $ticketPerAgent = 0;

                } else {
                    $db_field = mysqli_fetch_assoc($result);

                    // writeLog("[".date("Y-n-j H:i:s")."] check1a:".$query, "logSpeed_".$rndStr);

                    $agentTransfer = $db_field["agent_transfer"];
                    $ticketPerAgent = sanitize_int($db_field["ticket_per_agent"]);
                    $workspaceId = isset($json['workspaceId']) ? $json['workspaceId'] : null;
                    // per 22 januari, cari limit per channel
                    if ($workspaceId == "") {
                        // cari id workspace
                        $query1 = "SELECT workspace_id FROM botika_omny_workspace_bots WHERE bot_id = '" . $botId . "'";
                        // echo $query1;
                        $result1 = mysqli_query($mysqli, $query1);

                        $db_field1 = mysqli_fetch_assoc($result1);

                        // writeLog("[".date("Y-n-j H:i:s")."] check1b", "logSpeed_".$rndStr);

                        $workspaceId = $db_field1["workspace_id"];

                    } //if

                    // echo "workspaceId:".$workspaceId;
                    // exit;

                    if ($workspaceId != "") {

                        // cari setting
                        $query1 = "SELECT * FROM botika_omny_workspace_settings WHERE workspace_id = '" . $workspaceId . "'";
                        $result1 = mysqli_query($mysqli, $query1);

                        $db_field1 = mysqli_fetch_assoc($result1);

                        // writeLog("[".date("Y-n-j H:i:s")."] check1c", "logSpeed_".$rndStr);

                        // {"sla":[{"create":"system","status":"1","name":"Default SLA Policy","description":"default policy","priority":{"urgent":{"respond":{"value":"10","unit":"minute"},"resolve":{"value":"1","unit":"hour"},"operation":"business","email":"urgent"},"high":{"respond":{"value":"30","unit":"minute"},"resolve":{"value":"3","unit":"hour"},"operation":"business","email":"high"},"medium":{"respond":{"value":"60","unit":"minute"},"resolve":{"value":"6","unit":"hour"},"operation":"business","email":"medium"},"low":{"respond":{"value":"2","unit":"hour"},"resolve":{"value":"1","unit":"day"},"operation":"business","email":"low"}}}],"timezone":"Asia\/Jakarta","get_start":{"workspace_verified":true,"invite_team":false,"ticket":true,"integration":false},"agent":{"menu":{"access":"limited"},"transfer":"auto","ticket":{"access":"ticket only","total":"1","take":"false","channels":[{"name":["CHATBOTIKAWEBCHAT","BOTIKAWEBCHAT"],"limit":"127"},{"name":["TWITTER"],"limit":"127"},{"name":["WHATSAPP","OAWHATSAPP","OAWAPPIN","OAWHATSAPPJATIS","OAWHATSAPPTWILIO","OAWHATSAPPDAMCORP"],"limit":"127"},{"name":["TWITTERCOMMENT"],"limit":"127"},{"name":["EMAIL"],"limit":"127"},{"name":["TELEGRAM"],"limit":"127"},{"name":["FBMESSENGER"],"limit":"127"},{"name":["KAKAOTALK"],"limit":"127"},{"name":["FBCOMMENT"],"limit":"127"},{"name":["WECHAT"],"limit":"127"},{"name":["IGCOMMENT"],"limit":"127"},{"name":["ZALOOA"],"limit":"127"},{"name":["LINE"],"limit":"127"},{"name":["VIBER"],"limit":"127"},{"name":["ELIZA","PHONE"],"limit":"1","pauseOnCall":"true"},{"name":["WEBHOOK"],"limit":"127"}]}},"timework":{"sunday":[],"monday":[["08:00","17:00"]],"tuesday":[["08:00","17:00"]],"wednesday":[["08:00","17:00"]],"thursday":[["08:00","17:00"]],"friday":[["08:00","17:00"]],"saturday":[]}}
                        $workspaceSetting = $db_field1["setting"];

                        //cari limit channel ini, bila ada, maka assign ke ticketPerAgent
                        $workspaceSetting = json_decode($workspaceSetting, true);
                        // print_r($workspaceSetting);

                    } //if

                } //if

                // writeLog("[".date("Y-n-j H:i:s")."] check2", "logSpeed_".$rndStr);

                // echo $agentTransfer;
                // exit;

                if ($agentTransfer != "manual") {

                    $arrAssign = array();
                    $arrWait = array();
                    $wait = 0;

                    $arrAgent = array();
                    $arrAgentOri = array();
                    $cacheAgent = null;
                    //---------------------------------------------------------
                    //cari semua account_id untuk bot ini
                    // Fitur Auto Distribution dimana setiap email yang masuk ke sistem didistribusikan ke agent yang AVAILABLE ( masuk ke bucket agent ).
                    // Status : Available : Agent login dan siap menerima email yang masuk dan mereplynya
                    // Status : Not Available : Agent login, bisa mereply email di bucketnya, tidak ada email baru yang bisa masuk
                    // Status : Break : Agent login, tetapi kondisi beristirahat ( dibutuhkan untuk perhitungan performansi agent )
                    // Status : Offline : agent tidak login ( logout )

                    // $query = "select name, botika_accounts.account_id, login, login_status, last_active_date ".

                    $query = "select distinct name, ba.account_id, login, login_status, last_active_date, oml.value `channels`, bowa.departement_id as department_id, tbl.total_solved " .
                    "from botika_bot_access bba " .
                    "join botika_accounts ba on ba.account_id = bba.account_id " .
                    "left join botika_omny_multi_level_values oml on oml.agent_id = ba.account_id and oml.bot_id = '" . $botId . "' and oml.type = 'channel' " .
                    "left join botika_omny_workspace_access bowa ON ba.account_id = bowa.account_id " .
                    "left join (
						select ticket_assignee_id, COUNT(ticket_idx) AS total_solved
						from botika_helpdesk_tickets
						where ticket_status = 'solved' and bot_id = '$botId'
						and creation_date >= '" . date("Y-m-d 00:00:00") . "'
						group BY ticket_assignee_id
					) tbl ON bba.account_id = tbl.ticket_assignee_id " .
                    "where " .
                    "bba.bot_id = '" . $botId . "' and " .
                    "login = 1 and " .
                    "(bba.level = '' or bba.level is null or bba.level = 'agent') and " .
                    "(login_status = '' or login_status = 'available' or login_status is null) and " .
                    "last_active_date >= '" . date("Y-n-j H:i:s", strtotime("-30 minutes")) . "' group by account_id";

                    // echo $query;
                    // writeLog("[".date("Y-n-j H:i:s")."] check3a: ".$query, "logSpeed_".$rndStr);

                    $result = mysqli_query($mysqli, $query);

                    $workspaceDepart = [];
                    while ($db_field = mysqli_fetch_assoc($result)) {
                        $arrAgentOri[] = array(
                            "accountId" => $db_field["account_id"],
                            "channels" => $db_field["channels"],
                            "total" => 0,
                            "totalSolved" => (int) $db_field["total_solved"] ?? 0,
                            "lastActive" => $db_field["last_active_date"],
                        );

                        if (!in_array($db_field["department_id"], $workspaceDepart)) {
                            $workspaceDepart[] = $db_field["department_id"];
                        }

                    } //while

                    // writeLog("[".date("Y-n-j H:i:s")."] check3b arrAgentOri:".print_r($arrAgentOri, true), "logSpeed_".$rndStr);

                    //per 22 januari limit berdasar total channel
                    if (isset($workspaceSetting["agent"]["ticket"]["channels"])) {

                        //--------------------------------
                        //sort berdasar total tiket all channel
                        // function cmp($a, $b)
                        // {
                        //     return $a["totalAllChannel"] - $b["totalAllChannel"];
                        // } //function cmp($a, $b)

                        // per 17 maret 2022, bila jumlah sama, utamakan yg ticketnya paling lama
                        function cmp($a, $b)
                        {
                            // $c = $a['totalAllChannel'] - $b['totalAllChannel'];
                            // $c .= $a['ticket_date'] - $b['ticket_date'];
                            $c = ($a['totalAllChannel'] ?? 0) - ($b['totalAllChannel'] ?? 0);
                            if (isset($a['ticket_date']) && isset($b['ticket_date'])) {
                                $d = $a['ticket_date'] - $b['ticket_date'];
                                $c = $c * 10000000 + $d;
                            }
                            return $c;
                        }

                    } else {

                        $arrAgent = $arrAgentOri;

                        //cari ticket yg sedang dihandle agent
                        $query1 = "select bot_id, ticket_assignee_id, COUNT(ticket_assignee_id) AS total, botika_helpdesk_tickets.creation_date " .
                            "from botika_helpdesk_tickets " .
                            "where ticket_status IN ('unsolved', 'hold') and bot_id = '" . $botId . "' " .
                            "and ticket_assignee_id != '' " .
                            "group by bot_id, ticket_assignee_id";

                        $result1 = mysqli_query($mysqli, $query1);

                        if (mysqli_num_rows($result1) == 0) {

                            //do none

                        } else {
                            while ($db_field1 = mysqli_fetch_assoc($result1)) {

                                if ($db_field1["ticket_assignee_id"] != "") {

                                    for ($k = 0; $k < sizeof($arrAgent); $k++) {

                                        if ($arrAgent[$k]["accountId"] == $db_field1["ticket_assignee_id"]) {
                                            $arrAgent[$k]["total"] = $db_field1["total"];
                                            $arrAgent[$k]["ticket_date"] = strtotime($db_field1["creation_date"]);
                                        } //if

                                    } //for($k=0;$k<sizeof($arrAgent);$k++) {
                                }

                            } //while
                        } //if(mysqli_num_rows($result1) == 0){

                        //--------------------------------
                        //sort
                        // function cmp($a, $b)
                        // {
                        //     return $a["total"] - $b["total"];
                        // } //function cmp($a, $b)

                        // per 17 maret 2022, bila jumlah sama, utamakan yg ticketnya paling lama
                        function cmp($a, $b)
                        {
                            // $c = $a['total'] - $b['total'];
                            // $c .= $a['ticket_date'] - $b['ticket_date'];
                            $c = $a['total'] - $b['total'];
                            $d = $a['ticket_date'] - $b['ticket_date'];
                            $c = $c * 10000000 + $d;
                            return $c;
                        }
                        //--------------------------------

                    } //if

                    // writeLog("[".date("Y-n-j H:i:s")."] check4", "logSpeed_".$rndStr);

                    //---------------------------------------------------------
                    //cari semua ticket yg pending untuk bot ini
                    // $query = "select ticket_id, user_id, chat_log_id_start ".
                    //     "from botika_helpdesk_tickets ".
                    //     "where ".
                    //     "ticket_status = 'unassigned' and ".
                    //     "bot_id = '".$botId."' ".
                    //     "order by creation_date asc";

                    $available_channels = [];
                    foreach (array_column($arrAgentOri, 'channels') as $channels) {
                        $channels = $channels ? json_decode($channels, true) : null;

                        if (!$channels) {
                            continue;
                        }

                        foreach ($channels as $channel) {
                            if (!in_array($channel, $available_channels)) {
                                $available_channels[] = $channel;
                            }

                        }
                    }

                    if (count($available_channels) > 0) {
                        $result = [];
                        foreach ($available_channels as $channel) {
                            $query = "
								SELECT
									bht.ticket_idx,
									bht.ticket_id,
									bht.user_id,
									bht.chat_log_id_start,
									bcl.chat_type,
									bht.department_id,
									bht.creation_date
								FROM
									botika_helpdesk_tickets2 bht
								JOIN
									botika_chat_logs bcl ON bcl.chat_log_id = bht.chat_log_id_start
								WHERE
									ticket_status = 'unassigned'
									AND ticket_assignee_id IS NULL
									AND bht.bot_id = '$botId'
									AND bcl.chat_type = '$channel'
								ORDER BY
									bht.ticket_idx ASC
								LIMIT 10
							";
                            $tickets = mysqli_query($mysqli, $query);
                            $tickets = $tickets->fetch_all(MYSQLI_ASSOC);
                            $result = array_merge($result, $tickets);
                        }
                    } else {
                        $query = "select bht.ticket_idx, bht.ticket_id, bht.user_id, bht.chat_log_id_start, bcl.chat_type, bht.department_id, bht.creation_date  " .
                            "from botika_helpdesk_tickets2 bht " .
                            "join botika_chat_logs bcl ON bcl.chat_log_id = bht.chat_log_id_start " .
                            "where " .
                            "bht.ticket_status = 'unassigned' and " .
                            "bht.ticket_assignee_id IS NULL and " .
                            "bht.bot_id = '" . $botId . "' " .
                            "order by bht.ticket_idx asc LIMIT 25";

                        $result = mysqli_query($mysqli, $query);
                        $result = $result->fetch_all(MYSQLI_ASSOC);
                    }

                    # 16 Feb 24, tambah query per department antisipasi stuck antrian di salah satu department
                    if ($workspaceDepart) {
                        $tempTicket = $result ? array_column($result, 'ticket_idx') : [];
                        foreach ($workspaceDepart as $depId) {
                            $query = "select bht.ticket_idx, bht.ticket_id, bht.user_id, bht.chat_log_id_start, bcl.chat_type, bht.department_id, bht.creation_date  " .
                                "from botika_helpdesk_tickets2 bht " .
                                "join botika_chat_logs bcl ON bcl.chat_log_id = bht.chat_log_id_start " .
                                "where " .
                                "bht.bot_id = '" . $botId . "' and " .
                                "bht.ticket_status = 'unassigned' and " .
                                "bht.ticket_assignee_id IS NULL and " .
                                "bht.department_id = '" . $depId . "' ";

                            if ($tempTicket) {
                                $query .= "and bht.ticket_id not in ('" . implode("','", $tempTicket) . "')";
                            }

                            $query .= "order by bht.ticket_idx asc LIMIT 5";

                            $tickets = mysqli_query($mysqli, $query);
                            $tickets = $tickets->fetch_all(MYSQLI_ASSOC);
                            $result = array_merge($result, $tickets);
                        }
                    }

                    if ($result) {
                        $result = array_orderby($result, 'creation_date', SORT_ASC);
                    }

                    // echo json_encode([
                    //     'result' => $result,
                    // ]);
                    // exit;
                    $prevChatType = "";
                    $prevDepartmentId = "";

                    foreach ($result as $db_field) {
                        $ticketId = $db_field["ticket_id"];
                        $ticketCreationDate = $db_field["creation_date"];
                        $ticketIdx = $db_field["ticket_idx"];
                        $userId = $db_field["user_id"];
                        $chatLogId = $db_field["chat_log_id_start"];
                        $departmentId = $db_field["department_id"];

                        //echo $departmentId;
                        //exit;

                        //cari chatType
                        // $query1 = "select chat_type from botika_chat_logs where chat_log_id = '".$chatLogId."' ".
                        //     "and bot_id = '".$botId."' limit 0,1";

                        // // echo $query1;
                        // $result1 = mysqli_query($mysqli, $query1);
                        // $db_field1 = mysqli_fetch_assoc($result1);

                        // $chatType = $db_field1["chat_type"];
                        $chatType = $db_field["chat_type"];

                        //hanya update agent dan total tiket bila loop chat type sudah berubah
                        //bila belum berubah, maka cukup tambahkan counter total secara program
                        if (
                            ($prevChatType != $chatType) ||
                            ($prevDepartmentId != $departmentId)
                        ) {
                            // writeLog("[".date("Y-n-j H:i:s")."] check5 diff chatType:".$chatType, "logSpeed_".$rndStr);

                            //--------------------------------
                            if ($cacheAgent && isset($cacheAgent[$chatType . "_" . $departmentId]) && is_array($cacheAgent[$chatType . "_" . $departmentId])) {

                                //ambil dari cache yng berisi $cacheAgent = ['arrAgent' => $arrAgent, 'ticketPerAgent' => $ticketPerAgent]
                                $arrAgent = $cacheAgent[$chatType . "_" . $departmentId]['arrAgent'];
                                $ticketPerAgent = $cacheAgent[$chatType . "_" . $departmentId]['ticketPerAgent'];
                                // writeLog("[".date("Y-n-j H:i:s")."] cache:".print_r($cacheAgent[$chatType], true), "logSpeed_".$rndStr);
                                writeLog("[$uniqode - helpdeskticketassign] using old arrAgentCache: $chatType limit: $ticketPerAgent bot: $botId :" . json_encode($arrAgent), "logDebug_" . date("Y-m-d_H"));

                            } else {
                                //--------------------------------
                                //per 22 januari limit berdasar total channel
                                if (isset($workspaceSetting["agent"]["ticket"]["channels"])) {

                                    for ($i = 0; $i < sizeof($workspaceSetting["agent"]["ticket"]["channels"]); $i++) {

                                        for ($j = 0; $j < sizeof($workspaceSetting["agent"]["ticket"]["channels"][$i]["name"]); $j++) {

                                            if (strtolower($workspaceSetting["agent"]["ticket"]["channels"][$i]["name"][$j]) == "phone") {

                                                // khusus phone, check "pauseOnCall":"true"
                                                // {"name":["ELIZA","PHONE"],"limit":"1","pauseOnCall":"true"}
                                                if (strtoupper($workspaceSetting["agent"]["ticket"]["channels"][$i]["pauseOnCall"]) == "TRUE") {
                                                    $pauseOnCall = true;
                                                } //if

                                            } //if

                                            if (
                                                (strtolower($workspaceSetting["agent"]["ticket"]["channels"][$i]["name"][$j]) == strtolower($chatType)) &&
                                                (sanitize_int($workspaceSetting["agent"]["ticket"]["channels"][$i]["limit"]) > 0)
                                            ) {
                                                # jika ketemu, break double
                                                $ticketPerAgent = sanitize_int($workspaceSetting["agent"]["ticket"]["channels"][$i]["limit"]);
                                                writeLog("[$uniqode - helpdeskticketassign] Get new limit :$chatType limit: $ticketPerAgent bot: $botId - ", "logDebug_" . date("Y-m-d_H"));
                                                break 2;
                                            } //if

                                        } //for
                                    } //for
                                } //if

                                if (isset($workspaceSetting["agent"]["ticket"]["channels"])) {

                                    // writeLog("[".date("Y-n-j H:i:s")."] check5a permission per channel, reload agent array", "logSpeed_".$rndStr);

                                    // reset
                                    $arrAgent = [];

                                    //reset total
                                    for ($k = 0; $k < sizeof($arrAgentOri); $k++) {

                                        //check channel
                                        //kalau kosong = all
                                        //kalau {} = none

                                        $chanPermissionOk = false;
                                        $departmentPermissionOk = true;

                                        if ($departmentId != "") {

                                            // check apakah agent ada di department ini
                                            $query1 = "SELECT departement_id FROM botika_omny_workspace_access " .
                                                "WHERE workspace_id = '" . $workspaceId . "' AND " .
                                                "departement_id = '" . $departmentId . "' AND " .
                                                "account_id = '" . $arrAgentOri[$k]["accountId"] . "' ";
                                            $result1 = mysqli_query($mysqli, $query1);

                                            // writeLog("[".date("Y-n-j H:i:s")."] permission departmentId:".$departmentId, "logSpeed_".$rndStr);
                                            // exit;

                                            if (mysqli_num_rows($result1) == 0) {
                                                $departmentPermissionOk = false;
                                            } else {
                                                $departmentPermissionOk = true;
                                            } //if

                                            // writeLog("[".date("Y-n-j H:i:s")."] permission ok? ".$departmentPermissionOk, "logSpeed_".$rndStr);
                                        } //if

                                        if ($departmentPermissionOk == true) {
                                            /*
                                            // check apakah agent punya skill channel ini
                                            $query1 = "select value from botika_omny_multi_level_values " .
                                            "where agent_id = '" . $arrAgentOri[$k]["accountId"] . "' and type = 'channel' and bot_id = '$botId'";
                                            $result1 = mysqli_query($mysqli, $query1);

                                            // echo $query1."<BR>";

                                            if (mysqli_num_rows($result1) == 0) {
                                            $chanPermissionOk = true;
                                            } else {

                                            $db_field1 = mysqli_fetch_assoc($result1);

                                            $chanPermission = $db_field1["value"];

                                            if ($chanPermission == "") {
                                            //all
                                            $chanPermissionOk = true;
                                            } else if (
                                            ($chanPermission == "{}") ||
                                            ($chanPermission == "[]")
                                            ) {
                                            //none
                                            $chanPermissionOk = false;
                                            } else {
                                            $chanPermission = json_decode($chanPermission, true);

                                            for ($m = 0; $m < sizeof($chanPermission); $m++) {
                                            // echo strtolower($chanPermission[$m]).":".strtolower($chatType)."<BR>";
                                            if (strtolower($chanPermission[$m]) == strtolower($chatType)) {
                                            $chanPermissionOk = true;
                                            break; //$m
                                            } //if
                                            } //for
                                            } //if
                                            } //if
                                             */

                                            if ($arrAgentOri[$k]["channels"] == '') {
                                                $chanPermissionOk = true;
                                            } else if (
                                                ($arrAgentOri[$k]["channels"] == "{}") ||
                                                ($arrAgentOri[$k]["channels"] == "[]")
                                            ) {
                                                //none
                                                $chanPermissionOk = false;
                                            } else {
                                                $chanPermission = json_decode($arrAgentOri[$k]["channels"], true);

                                                for ($m = 0; $m < sizeof($chanPermission); $m++) {
                                                    // echo strtolower($chanPermission[$m]).":".strtolower($chatType)."<BR>";
                                                    if (strtolower($chanPermission[$m]) == strtolower($chatType)) {
                                                        $chanPermissionOk = true;
                                                        break; //$m
                                                    } //if
                                                } //for
                                            }

                                            if ($chanPermissionOk) {
                                                $arrAgent[] = array(
                                                    "accountId" => $arrAgentOri[$k]["accountId"],
                                                    "total" => 0,
                                                    "totalSolved" => $arrAgentOri[$k]['totalSolved'],
                                                    "lastActive" => $arrAgentOri[$k]['lastActive'],
                                                );
                                            } //if
                                        } //if
                                    } //for

                                    // writeLog("[".date("Y-n-j H:i:s")."] check6 arrAgent:".print_r($arrAgent, true), "logSpeed_".$rndStr);

                                    //ambil total tiket yg dihandle per agent
                                    $query1 = "select ticket_assignee_id, botika_chat_logs.chat_type, COUNT(ticket_assignee_id) AS total, botika_helpdesk_tickets.creation_date " .
                                        "from botika_helpdesk_tickets " .
                                        "join botika_chat_logs ON botika_helpdesk_tickets.chat_log_id_start = botika_chat_logs.chat_log_id " .
                                        "where ticket_status IN ('unsolved', 'hold') and botika_helpdesk_tickets.bot_id = '" . $botId . "' " .
                                        "and ticket_assignee_id != '' " .
                                        "GROUP BY ticket_assignee_id, botika_chat_logs.chat_type " .
                                        "ORDER BY total, botika_helpdesk_tickets.creation_date asc";

                                    // writeLog("[".date("Y-n-j H:i:s")."] ".$query1, "logSpeed_".$rndStr);

                                    $result1 = mysqli_query($mysqli, $query1);

                                    if (mysqli_num_rows($result1) == 0) {

                                        //do none

                                    } else {
                                        while ($db_field1 = mysqli_fetch_assoc($result1)) {

                                            // writeLog("[".date("Y-n-j H:i:s")."] === loop db record", "logSpeed_".$rndStr);

                                            if ($db_field1["ticket_assignee_id"] != "") {

                                                for ($k = 0; $k < sizeof($arrAgent); $k++) {

                                                    // echo $chatType."<BR>";
                                                    // echo $arrAgent[$k]["accountId"].":".$db_field1["ticket_assignee_id"]."<BR>";
                                                    // writeLog("[".date("Y-n-j H:i:s")."] loop ".$k.":".$arrAgent[$k]["accountId"].":".$db_field1["ticket_assignee_id"], "logSpeed_".$rndStr);

                                                    if ($arrAgent[$k]["accountId"] == $db_field1["ticket_assignee_id"]) {

                                                        // writeLog("[".date("Y-n-j H:i:s")."] match ".$arrAgent[$k]["accountId"]." ===", "logSpeed_".$rndStr);
                                                        // writeLog("[".date("Y-n-j H:i:s")."] check ".(strtolower($db_field1["chat_type"]).":".strtolower($chatType)), "logSpeed_".$rndStr);

                                                        if (strtolower($db_field1["chat_type"]) == strtolower($chatType)) {
                                                            // writeLog("[".date("Y-n-j H:i:s")."] set total:".$db_field1["total"], "logSpeed_".$rndStr);
                                                            $arrAgent[$k]["total"] = sanitize_int($db_field1["total"]);
                                                        } //if

                                                        if (
                                                            isset($db_field1["creation_date"]) &&
                                                            (!isset($arrAgent[$k]["ticket_date"]) || ($db_field1["creation_date"] >= $arrAgent[$k]["ticket_date"]))
                                                        ) {
                                                            $arrAgent[$k]["ticket_date"] = strtotime($db_field1["creation_date"]);
                                                        }

                                                        //tambahkan di totalAllChannel
                                                        $arrAgent[$k]["totalAllChannel"] =
                                                        (sanitize_int($arrAgent[$k]["totalAllChannel"] ?? 0)) +
                                                        sanitize_int($db_field1["total"]);

                                                        // writeLog("[".date("Y-n-j H:i:s")."] set totalAllChannel:".$arrAgent[$k]["totalAllChannel"], "logSpeed_".$rndStr);

                                                        // jika sudah lebih atau sama dgn ticketPerAgent, maka letakkan di paling bawah
                                                        if ($arrAgent[$k]["total"] >= $ticketPerAgent) {

                                                            // writeLog("[".date("Y-n-j H:i:s")."] agent full:".$arrAgent[$k]["accountId"], "logSpeed_".$rndStr);
                                                            // writeLog("[".date("Y-n-j H:i:s")."] total now:".$arrAgent[$k]["total"], "logSpeed_".$rndStr);
                                                            // writeLog("[".date("Y-n-j H:i:s")."] limit:".$ticketPerAgent, "logSpeed_".$rndStr);

                                                            $arrAgent[$k]["total"] = 9999;
                                                            $arrAgent[$k]["totalAllChannel"] = 9999;
                                                        } //if

                                                        //jika handle phone, dan pauseOnCall = true, maka anggap total penuh
                                                        if (
                                                            strtolower($db_field1["chat_type"]) == "phone") {
                                                            if ($pauseOnCall == true) {

                                                                writeLog("[" . date("Y-n-j H:i:s") . "] phone found, not taking any ticket", "logDebug_" . date("Y-m-d_H"));

                                                                $arrAgent[$k]["total"] = 9999;
                                                                $arrAgent[$k]["totalAllChannel"] = 9999;
                                                            } //if
                                                        } //if

                                                    } //if

                                                } //for($k=0;$k<sizeof($arrAgent);$k++) {
                                            }
                                        } //while
                                    } //if

                                } //if
                            } //if(is_array($cacheAgent[$chatType])) {
                        } else {
                            // writeLog("[".date("Y-n-j H:i:s")."] same chatType:".$chatType, "logSpeed_".$rndStr);
                        } //if($prevChatType!=$chatType) {

                        // writeLog("[".date("Y-n-j H:i:s")."] check7", "logSpeed_".$rndStr);
                        //--------------------------------
                        //sort lagi krn tiket pending bisa beberapa
                        //agar distribusi selalu ke agent yg paling sedikit
                        // usort($arrAgent, "cmp");

                        // per 17 maret 2022, bila jumlah sama, utamakan yg ticketnya paling lama
                        uasort($arrAgent, "cmp");
                        $arrAgent = array_values($arrAgent);

                        // per 10 juni 2024, ubah sorting menjadi 4 sorting
                        $arrAgent = array_orderby($arrAgent, 'total', SORT_ASC, 'totalSolved', SORT_ASC, 'ticket_date', SORT_ASC);
                        $arrAgent = array_values($arrAgent);

                        // print_r($arrAgent);

                        // 27 Juli 2022
                        // check apakah ticket sudah berubah status
                        $queryCheck = "SELECT * FROM botika_helpdesk_tickets " .
                            "WHERE ticket_id = '" . $ticketId . "' " .
                            "AND ticket_status = 'unassigned' ";
                        $resultCheck = mysqli_query($mysqli, $queryCheck);
                        $countCheck = mysqli_num_rows($resultCheck);

                        // echo $ticketPerAgent;
                        // exit;
                        //--------------------------------
                        //bila ticket per agent lebih kecil dari agent yg paling sedikit menghandle ticket,
                        //maka lanjut
                        // echo json_encode([
                        //     'arrAgent' => $arrAgent,
                        //     'ticketPerAgent' => $ticketPerAgent,
                        //     'countCheck' => $countCheck,
                        // ]);
                        // die;
                        if ((sizeof($arrAgent) > 0) && ($arrAgent[0]["total"] < $ticketPerAgent) && $countCheck == 1) {
                            // 1 Feb 2024, tambahkan pengecekan status agent
                            $queryCheck = "SELECT account_id, login, login_status, last_active_date FROM botika_accounts WHERE account_id = '" . $arrAgent[0]["accountId"] . "'";
                            $queryAgent = mysqli_query($mysqli, $queryCheck);
                            $checkAgent = mysqli_fetch_assoc($queryAgent);
                            // jika tidak ada data, shift agent dan lanjut query
                            if (
                                !$checkAgent ||
                                ($checkAgent && ($checkAgent['login'] != 1 || ($checkAgent['login'] == 1 && ($checkAgent['last_active_date'] <= date("Y-m-d H:i:s", strtotime("-30 minutes")) || strtolower($checkAgent['login_status']) != 'available'))))
                            ) {
                                writeLog("[$uniqode - helpdeskticketassign] Agent not available, removing agent: " . json_encode($checkAgent), "logDebug_" . date("Y-m-d_H"));
                                array_shift($arrAgent);
                                $prevChatType = $chatType;
                                $prevDepartmentId = $departmentId;
                                continue;
                            }

                            $lockAssign = checkLock2("assign_$botId-$ticketId", 300, 1);
                            writeLock("assign_$botId-$ticketId");
                            if (!$lockAssign) {
                                writeLog("[$uniqode - helpdeskticketassign] Ticket: $ticketId already assigned, continue", "logDebug_" . date("Y-m-d_H"));
                                $prevChatType = $chatType;
                                $prevDepartmentId = $departmentId;
                                continue;
                            }

                            // lock assign per agent jeda maksimal 1 detik
                            $lockAgent = checkLock2("assign_$botId-" . $arrAgent[0]["accountId"], 3, 1);
                            writeLock("assign_$botId-" . $arrAgent[0]["accountId"]);
                            if (!$lockAgent) {
                                writeLog("[$uniqode - helpdeskticketassign] Agent: " . $arrAgent[0]["accountId"] . " already assigned, continue", "logDebug_" . date("Y-m-d_H"));
                                array_shift($arrAgent);
                                $prevChatType = $chatType;
                                $prevDepartmentId = $departmentId;
                                continue;
                            }

                            writeLog("[$uniqode - helpdeskticketassign] Log round robin: $chatType limit: $ticketPerAgent bot: $botId - " . json_encode($arrAgent), "logDebug_" . date("Y-m-d_H"));
                            //if($arrAgent[0]["total"]<$ticketPerAgent) {

                            //update jumlah total yg dihandle
                            // $arrAgent[0]["total"] = $arrAgent[0]["total"] + 1;
                            // if($arrAgent[0]["totalAllChannel"]!="") {
                            //     $arrAgent[0]["totalAllChannel"] = $arrAgent[0]["totalAllChannel"] + 1;
                            // } //if

                            //assign ke agent yang paling sedikit
                            $query1 = "update botika_helpdesk_tickets set " .
                                "ticket_assignee_id = '" . $arrAgent[0]["accountId"] . "', " .
                                "ticket_status = 'unsolved' " .
                                "where ticket_id = '" . $ticketId . "' ";

                            //echo $query;
                            $result1 = mysqli_query($mysqli, $query1);

                            $timestampNow = strtotime('now');

                            // per 30 september 2022 ubah pengecekan dengan query mengantisipasi api jalan bersamaan
                            // update jumlah total yg dihandle
                            $query1 = "select ticket_assignee_id, botika_chat_logs.chat_type, COUNT(ticket_assignee_id) AS total, botika_helpdesk_tickets.creation_date " .
                                "from botika_helpdesk_tickets " .
                                "LEFT JOIN botika_chat_logs ON botika_helpdesk_tickets.chat_log_id_start = botika_chat_logs.chat_log_id " .
                                "where ticket_status IN ('unsolved', 'hold') and botika_helpdesk_tickets.bot_id = '" . $botId . "' " .
                                "and ticket_assignee_id = '" . $arrAgent[0]["accountId"] . "' " .
                                "GROUP BY ticket_assignee_id, botika_chat_logs.chat_type " .
                                "ORDER BY ticket_assignee_id asc";
                            $result1 = mysqli_query($mysqli, $query1);
                            $total1 = 0;
                            while ($db_field1 = mysqli_fetch_assoc($result1)) {
                                # 29 maret 2023, jika ticket per chat type sama dengan limit, set jadi 9999
                                if ($db_field1['chat_type'] >= $ticketPerAgent) {
                                    $arrAgent[0]["total"] = 9999;
                                } elseif ($db_field1['chat_type'] == $chatType) {
                                    $arrAgent[0]["total"] = $db_field1["total"];
                                }
                                $total1 += $db_field1["total"];
                            }
                            if (!isset($arrAgent[0]["totalAllChannel"]) || $arrAgent[0]["totalAllChannel"] != "") {
                                $arrAgent[0]["totalAllChannel"] = $total1;
                            } //if
                            $arrAgent[0]["ticket_date"] = strtotime('now');

                            $arrAssign[] = array(
                                "ticketId" => $ticketId,
                                "userId" => $userId,
                                "accountId" => $arrAgent[0]["accountId"],
                            );

                            writeLog("[$uniqode - helpdeskticketassign] Assigned ticket $ticketId to " . $arrAgent[0]['accountId'], "logDebug_" . date("Y-m-d_H"));

                            //agent
                            $queryAgent = "select account_id, username, name from botika_accounts where account_id  = '" . $arrAgent[0]['accountId'] . "'";
                            $resultAgent = mysqli_query($mysqli, $queryAgent);
                            $agentData = $resultAgent->fetch_all(MYSQLI_ASSOC);

                            $agent = $agentData[0]['account_id'];
                            $agentName = $agentData[0]['name'];

                            # per 5 Feb 24, ubah agar insert analytic & report omni dihandle oleh helpdesk
                            $contentReport = [
                                'date' => date("Y-m-d H:i:s", strtotime($timestampNow)),
                                'ticket_id' => $ticketId,
                                'to_id' => $arrAgent[0]["accountId"],
                                'to_name' => $agentName,
                                'ticket_status' => 'unsolved',
                            ];

                            $key = true;
                            while ($key) {
                                $analyticId = randString();
                                $analyticQuery = "select analytic_id from botika_analytics where analytic_id = '$analyticId'";
                                $checkAnalyticId = mysqli_query($mysqli, $analyticQuery);
                                if (mysqli_num_rows($checkAnalyticId) < 1) {
                                    $key = false;
                                }
                            }

                            $insertAnalytic = "insert into botika_analytics (analytic_id, analytic_group, analytic_key, analytic_value, analytic_date, bot_id, account_id, analytic_value_new)
								values ('$analyticId', 'Start AART', '$ticketId', '$timestampNow', '" . date('Y-m-d H:00:00', $timestampNow) . "', '$botId', '" . $arrAgent[0]["accountId"] . "', '$timestampNow')";
                            $result = mysqli_query($mysqli, $insertAnalytic);
                            $lastid = mysqli_insert_id($mysqli);

                            $insertNewAnalytic = "INSERT INTO botika_omny_analytic_details (ticket_idx, `type`, `start`, `creation_date`, `bot_id`, `ticket_creation_date`) VALUES ('$ticketIdx', 'Agent Response Time', '$timestampNow', '" . date('Y-m-d H:00:00') . "', '$botId', '$ticketCreationDate')";
                            $resultNew = mysqli_query($mysqli, $insertNewAnalytic);
                            writeLog('insert new analytic :' . $insertNewAnalytic . ' result : ' . $resultNew, 'LogAnalytic');
                            //kalau tidak ada, maka buat random
                            //create random string
                            $milliseconds = round(microtime(true) * 1000);
                            $reportId = $milliseconds;
                            $reportId = $reportId . $botId;
                            $reportId = substr(md5($reportId), 0, 10);

                            $insertReport = "insert into botika_reports (report_id, report_group, report_title, report_content, bot_id, account_id, report_date, creation_date)
								values ('$reportId', 'Ticket Routing', '$ticketId', '" . json_encode($contentReport) . "', '$botId', '" . $arrAgent[0]["accountId"] . "', '" . date('Y-m-d 00:00:00', $timestampNow) . "', '" . date('Y-m-d H:i:s', $timestampNow) . "')";
                            $result = mysqli_query($mysqli, $insertReport);
                            $lastid = mysqli_insert_id($mysqli);

                            // socket data
                            //------------------------------------------------------
                            $querySetup = 'select agent_chat_access from botika_bot_settings where bot_id = "' . $botId . '"';
                            // $resultSetup = mysqli_query($mysqli, $queryAcc);
                            $resultSetup = mysqli_query($mysqli, $querySetup);
                            // $fetchSetup = $resultAcc->fetch_all(MYSQLI_ASSOC);
                            $fetchSetup = $resultSetup->fetch_all(MYSQLI_ASSOC);
                            $chatAccess = $fetchSetup[0]['agent_chat_access'];

                            if ($chatAccess == 'all') {
                                //sendTo
                                $queryAcc = "select ba.access_token from botika_bots bb, botika_accounts ba ". 
                                        "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and login = '1' and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32 ".
                                        "union ".
                                        "select ba.access_token from botika_bot_access bb, botika_accounts ba ".
                                        "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and login = '1' and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32";
                                $resultAcc = mysqli_query($mysqli, $queryAcc);
                                $sendSocketAccId = $resultAcc->fetch_all(MYSQLI_NUM);
                            } else {
                                $tempIdAgent = $arrAgent[0]["accountId"];
                                $queryAcc = "select ba.access_token from botika_bots bb, botika_accounts ba ".
                                    "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and login = '1' and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32 ".
                                    "union ".
                                    "select ba.access_token from botika_bot_access bb, botika_accounts ba ". 
                                    "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and ba.account_id = '$tempIdAgent' and (bb.level = 'agent' or bb.level IS NULL) ".
                                    "union ".
                                    "select ba.access_token from botika_bot_access bb, botika_accounts ba ". 
                                    "where ba.account_id = bb.account_id and bb.bot_id = '$botId' and bb.level = 'supervisor' and login = '1' and TIMESTAMPDIFF(MINUTE, ba.last_active_date, CONVERT_TZ(now(), 'UTC','Asia/Jakarta')) < 32";
                                $resultAcc = mysqli_query($mysqli, $queryAcc);
                                $sendSocketAccId = $resultAcc->fetch_all(MYSQLI_NUM);
                            }

                            /*
                            if($chatAccess == 'all'){
                            //do nothing
                            } else {
                            //sendTo
                            $queryAcc = 'select ba.access_token from botika_bots bb, botika_accounts ba where ba.account_id = bb.account_id and bb.bot_id = "'.$botId.'" '.
                            ' union '.
                            'select ba.access_token from botika_bot_access bb, botika_accounts ba where ba.account_id = bb.account_id and bb.bot_id = "'.$botId.'" '.
                            ' and ba.account_id = "'.$arrAgent[0]["accountId"].'" and (bb.level = "agent" or bb.level IS NULL)'.
                            ' union '.
                            'select ba.access_token from botika_bot_access bb, botika_accounts ba where ba.account_id = bb.account_id and bb.bot_id = "'.$botId.'" '.
                            ' and bb.level = "supervisor" ';
                            $resultAcc = mysqli_query($mysqli, $queryAcc);
                            $sendSocketAccId = $resultAcc->fetch_all(MYSQLI_NUM);
                            }
                             */

                            //users
                            $queryUser = 'select user_id userId, concat(first_name,\' \', last_name) name, profile_pic profPic from botika_users where user_id = "' . $userId . '"';
                            $resultUser = mysqli_query($mysqli, $queryUser);
                            $user = $resultUser->fetch_all(MYSQLI_ASSOC);

                            // send to socket
                            //------------------------------------------------------
                            if (!empty($user)) {
                                $socketData["user"] = $user[0];
                            } else {
                                $socketData["user"]["userId"] = $userId;
                            } //if

                            $socketData["action"] = "change"; //chat or ticket
                            $socketData["ticketId"] = $ticketId;
                            $socketData["ticketStatus"] = "unsolved";
                            $socketData["contentType"] = "ticket"; //chat or ticket
                            $socketData["botId"] = $botId;
                            $socketData["messengerType"] = $chatType;
                            $socketData["agent"] = $arrAgent[0]['accountId'];
                            $socketData["agentName"] = $agentName;
                            $socketData["sendTo"] = $sendSocketAccId;
                            $socketData["identifier"] = $uniqode;
                            $socketData["actionHelpdesk"] = 'assign';

                            if (isset($pastAgent) && !empty($pastAgent) && $pastAgent !== $assignTo) {
                                $socketData["agentPast"] = $pastAgent;
                            }

                            $jsonDataSocket = json_encode($socketData);

                            // $url = 'https://socket.botika.online/send/request';
                            // $token = '10b011f0-0b0c-4ecb-98d0-667057667ad9';

                            //kirim json ke socket
                            // $socket = getContentAsyncToken($url, $jsonDataSocket, $token );
                            // $socket = guzzleRequest($url, $jsonDataSocket, ["Token" => $token]);
                            // $header = '{"Token":"'.$token.'"}';
                            // $command = "/usr/bin/php ".realpath('.')."/guzzle_async.php";
                            // $command = $command." '".$url."' '".$jsonDataSocket."' '".$header."'";
                            // $command = $command." >> /dev/null &";
                            // $socket = shell_exec($command);
                            // Trigger socket
                            socket()->trigger('bot-' . $botId, 'ticket:update', $socketData);
                            //-------------------------------------------------------
                        } else if ($countCheck == 1) {

                            $wait = $wait + 1;

                            //semua penuh
                            //infokan urutannya
                            $arrWait[] = array(
                                "ticketId" => $ticketId,
                                "userId" => $userId,
                                "wait" => $wait,
                            );
                        } //if($ticketPerAgent<$arrAgent[0]["total"]) {

                        $prevChatType = $chatType;
                        $prevDepartmentId = $departmentId;
                        //-------------------------------------------------------
                        //update cache agar tidak query db lagi
                        $cacheAgent[$chatType . "_" . $departmentId] = ['arrAgent' => $arrAgent, 'ticketPerAgent' => $ticketPerAgent];
                        //-------------------------------------------------------
                        // writeLog("[".date("Y-n-j H:i:s")."] check8", "logSpeed_".$rndStr);

                    } //while($db_field = mysqli_fetch_assoc($result)) {

                    $tempArr["result"] = "success";
                    $tempArr["ticket_assigned"] = $arrAssign;
                    $tempArr["ticket_queue"] = $arrWait;

                    echo json_encode($tempArr);

                } else {

                    echo '{"result":"success"}';

                } //if($agentTransfer!="manual") {
                //-------------------------------------------------------
            }
        } //if

        // writeLog("[".date("Y-n-j H:i:s")."] check9", "logSpeed_".$rndStr);

        //---------------------------
        //per 31 jan 2024 pindah hapus lock supaya tidak double assign
        deleteLock("assign_$botId");
        //---------------------------

    } else if ($json["action"] == "helpdeskticketupdateend") {

        //untuk update chat log id end pada ticket yg sudah tertutup

        require_once "inc-db.php";

        $botId = sanitize_paranoid_string($json["botid"]);
        $chatId = sanitize_paranoid_string($json["chatid"]);
        $userId = sanitize_paranoid_string($json["userid"]);

        writeLog("[$uniqode - helpdeskticketupdateend] Receiving request " . json_encode($json) . " at : " . date("Y-m-d H:i:s"), "logDebug_" . date("Y-m-d_H"));

        //log time request diterima
        $logTime = date("Y-m-d H:i:s");

        if (
            ($botId == "") ||
            ($chatId == "") ||
            ($userId == "")
        ) {
            echo '{"result":"error"}';
        } else {
            // cari chat id dan menanggulangi chat belum masuk ke database
            $try = 0;
            $db_field = [];
            $timeMin = strtotime('-30 minutes');
            while ($try < 3 && empty($db_field)) {
                sleep(2);
                //cari chat id saat ini
                $query = "select chat_log_id, chat_log_idx, chat_type from botika_chat_logs where user_id = '" . $userId . "' " .
                    "and bot_id = '" . $botId . "' and chat_id = '" . $chatId . "' order by chat_log_idx desc limit 0, 1";
                $result = mysqli_query($mysqli, $query);
                $try++;

                if (mysqli_num_rows($result) == 0) {
                    continue;
                }

                $db_field = mysqli_fetch_assoc($result);
            }

            // echo $query;
            // exit;
            if (!empty($db_field)) {
                $chatLogIdEnd = $db_field["chat_log_id"];
                $chatLogIdxEnd = $db_field["chat_log_idx"];
                $chatType = $db_field['chat_type'];

                $query = "SELECT ticket_id from botika_helpdesk_tickets " .
                    "where bot_id = '" . $botId . "' and user_id = '" . $userId . "' and chat_id = '" . $chatId . "' and (ticket_status = 'solved' or ticket_status = 'closed') " .
                    "ORDER BY ticket_idx DESC LIMIT 0, 1";
                $result = mysqli_query($mysqli, $query);
                
                // jika kosong, ambil ticket terakhir saja asalkan bukan email / comment
                if (mysqli_num_rows($result) == 0 && !in_array($chatType, ['EMAIL', 'EMAILOUTLOOK', 'IGCOMMENT', 'FBCOMMENT', 'TWITTERCOMMENT', 'YTCOMMENT', 'GOOGLEPLAY', 'GOOGLEBUSINESS'])) {
                    $query = "SELECT ticket_id from botika_helpdesk_tickets " .
                        "where bot_id = '" . $botId . "' and user_id = '" . $userId . "' and chat_type2 = '" . $chatType . "' and (ticket_status = 'solved' or ticket_status = 'closed') " .
                        "ORDER BY ticket_idx DESC LIMIT 0, 1";
                    $result = mysqli_query($mysqli, $query);
                }

                if (mysqli_num_rows($result) != 0) {
                    $db_field = mysqli_fetch_assoc($result);
                    $ticketId = $db_field["ticket_id"];

                    $query = "update botika_helpdesk_tickets set
                    chat_log_idx_end = '" . $chatLogIdxEnd . "', " . "chat_log_id_end = '" . $chatLogIdEnd . "' " .
                        "where ticket_id = '" . $ticketId . "' ";
                    // echo $query;
                    // exit;
                    $result = mysqli_query($mysqli, $query);

                    writeLog("[$uniqode - helpdeskticketupdateend] Updating chatId End $ticketId ID end $chatLogIdEnd", "logDebug_" . date("Y-m-d_H"));
                } //if
            } else {
                writeLog("[$uniqode - helpdeskticketupdateend] Failed to getting chatId at helpdeskticketupdateend " . json_encode($json) . " at : " . $logTime, "logDebug_" . date("Y-m-d_H"));
            }

            echo '{"result":"success"}';
        }
    } else if ($json["action"] == "helpdeskticketcheck") {

        /**
         * API untuk mengecek apakah ada ticket yang belum solved
         */

        require_once "inc-db.php";

        $botId = sanitize_paranoid_string($json["botid"] ?? null);
        $userId = sanitize_paranoid_string($json["userid"] ?? null);
        $subject = sanitize_paranoid_string($json["subject"] ?? null);

        if (
            ($botId == "") ||
            ($userId == "")
        ) {
            echo json_encode([
                'result' => 'error',
                'errors' => [
                    'botid' => 'required',
                    'userid' => 'required',
                ],
                'message' => 'Bot ID and User ID required',
            ]);
        } else {

            $query = "SELECT *
				FROM botika_helpdesk_tickets
				WHERE bot_id = '$botId'
					AND user_id = '$userId'
					AND ticket_status IN ('unassigned', 'unsolved', 'hold')
			";

            if ($subject) {
                $query .= '
					AND ticket_group = "'.$subject.'"
				';
            }

            $query .= " LIMIT 1";

            $result = mysqli_query($mysqli, $query);
            $db_field = mysqli_fetch_assoc($result);

            if (!$db_field) {
                echo json_encode([
                    'result' => 'error',
                    'errors' => [],
                    'message' => 'User has no active tickets',
                ]);
            } else {
                echo json_encode([
                    'result' => 'success',
                    'message' => 'User has active ticket',
                    'data' => $db_field,
                ]);
            }

        }
    }

} else { //if
    echo json_encode([
        'result' => 'error',
        'message' => 'Invalid Token',
    ]);
}
//-------------------------------
$endTime = strtotime("now");
$diffTime = $endTime - $startTime;

if (abs($diffTime) > 5) {
    writeLog("[" . date("Y-n-j H:i:s") . "] " . $rndStr . " diffTime:" . $diffTime, "logSlow_" . date("Y-n-j_H"));
    writeLog("[" . date("Y-n-j H:i:s") . "] " . json_encode($json), "logSlow_" . date("Y-n-j_H"));
} //if
//-------------------------------2

// Example: vulnerable to SQL injection
$conn = new mysqli("localhost", "username", "password", "testdb");

$username = $_GET['username'];
$password = $_GET['password'];

$sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "Login successful!";
} else {
    echo "Invalid credentials.";
}

$conn->close();


