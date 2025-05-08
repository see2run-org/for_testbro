<?php
// Enter your code here
    
    $msg = strtolower("{{user.message}}");

    # transcriptGet()
    $data = array();
 

//     $param = array(
//         "id" => "{{id}}",
//         "message" => "{{user.message}}",
//         "history" => $data,
//         "user_id" => "{{user.id}}",
//         "mode" => "precision",
//         "prompt_filter" => true,
//         "llm_model" => "gpt-4o"
//     );

    // Step 1: Call custom_search API and get the insight using jsonApi
    $kb_url = "http://20.24.x:7580/faq";
    $kb_header = array(
        "Content-Type: application/json"
    );
    $kb_param = array(
        "id" => "Bjknhhvsiyc_insight",
        "message" => $msg,
        "fields" => array("question", "insight", "condition", "specific_time")
    );
    $kb_response = jsonApi("POST", $kb_url, $kb_header, json_encode($kb_param));
    $kb_response = json_decode($kb_response, true);
    $insight = "";
    if (isset($kb_response["data"][0]["insight"])) {
        $insight = $kb_response["data"][0]["insight"];
    }
    log("hasil extract insight", $insight);

    $url = "http://34.124.xx:7505/v1/chat/completions";
    $header = array(
        "Authorization: Bearer 2w478v-9xbh-uzpaspaig3b6jytj-55h892djw0-d2323awd",
        "Content-Type: application/json"
    );

    // Build messages array one by one to avoid structure issues
    $data = array();
    $data[] = array(
        "role" => "default",
        "content" => "You are a helpful assistant. Your task is to examine whether the given insight inside embedded additional knowledge is related to user needs.\nYou need to examine carefully whether the user question is relevant or not with the given insight knowledges, whether the given insight can answering the user question or not.\nDon\"t forget to look into the date and time of the insight, is it the same time with the user question or not, Be strict on this thing since you can give unrelevant output.\nIf the insight is not related to the user question, return \"0\". If the insight is related to the user question, return \"1\".\nYou must adhere the JSON format below to give the output:\n\n{\"reason\": \"reason why the insight is related or not to the user question\",\n\"is_related\": \"0 or 1\",\n\"language\": \"what language is the user used is asking and the language you should use to answering the questions. Indonesia, English or Others\",\n\"answer\": \"answer from the insight if the insight is related to the user question, if not related, return empty string\"}\n\nGive your output in JSON format only, without any opening sentence or explanations, for the answer parameters, give insightful, complete, and clean response if it\"s related."
    );
    $data[] = array(
        "role" => "default",
        "content" => "Use the following context as your new additional resource of knowledge:\n$insight"
    );
    $data[] = array(
        "role" => "user",
        "content" => $msg
    );

    $param = array(
        "messages" => $data,
        "model" => "gpt-4o",
        "prompt_filter" => false,
        "parameters" => array(
            "temperature" => 0,
            "response_format" => array("type" => "json_object")
        )
    );

    userVariableSet("param", json_encode($param));
    log("param GPT Partial - {{user.id}}", "{{param}}");
    $chat_response = jsonApi("POST", $url, $header, json_encode($param));
	log("hasil", $chat_response);
    $chat_content = "";
    $response = json_decode($chat_response, true);
    if (isset($response["choices"][0]["message"]["content"])) {
        $chat_content = trim($response["choices"][0]["message"]["content"]);
        $chat_content = json_decode($chat_content, true);
        if (isset($chat_content["is_related"]) && $chat_content["is_related"] == "1") {
            $chat_content = $chat_content["answer"];
        } else {
            $chat_content = $chat_content["answer"];
        }
        respondText($chat_content);

    }
   
    thenStop();
