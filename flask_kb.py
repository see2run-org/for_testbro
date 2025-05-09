@app.route('/kb/custom_search', methods=['POST'])
def custom_search():
    #ini functions
    data = request.get_json()
    try:
        message = data['message']
        # Check Bot Id and Uppercase Name
        is_class_id = is_json_key_present(data, 'id')
        if not is_class_id:
            return jsonify({'message': 'please specify class id', 'result': 'failed'})
        class_id = data['id']
        class_id = is_class_name_uppercase(class_id)

        # Check if fields are specified
        is_fields = is_json_key_present(data, 'fields')
        if not is_fields:
            return jsonify({'message': 'please specify fields to search', 'result': 'failed'})
        fields = data['fields']

        # Check whether the "limit" parameter is set or not, if not then make the default value with 4
        is_limit = is_json_key_present(data, 'limit')
        if not is_limit:
            limit_count = 4
        else:
            limit_count = data['limit']
        
        if not message:
            return jsonify({'data': 'please input search query, message can\'t be an empty value', 'result': 'failed' })

        # sanitize input
        message = message.strip()
        message = message.replace("  ", " ")
        
        # Process message with Azure OpenAI to extract field values
        field_values = extract_field_values_with_azure(message)

        field_values= json.loads(field_values)
        
        condition_list = field_values['condition']
        specific_time_list = field_values['specific_time']
        
        # Use the extracted field values for search
        nearText = {"concepts": message}

        # Check if the request have a treshold needed for searching the KB
        is_sim_treshold = is_json_key_present(data, 'treshold')
        if is_sim_treshold:
            nearText["certainty"] = data["treshold"]

        class_id_kb = class_id + "_kb"
        
        query = client.query.get(class_id_kb, fields)
        query = query.with_near_text(nearText).with_limit(limit_count).with_additional(["id", "certainty"])
        
        # Build where filters based on non-empty lists
        where_filters = []
        
        # Add condition filter if condition_list is not empty
        if condition_list and len(condition_list) > 0:
            where_filters.append({
                "path": ["condition"],
                "operator": "ContainsAny",
                "valueTextArray": condition_list
            })
        
        # Add specific_time filter if specific_time_list is not empty
        if specific_time_list and len(specific_time_list) > 0:
            where_filters.append({
                "path": ["specific_time"],
                "operator": "ContainsAny",
                "valueTextArray": specific_time_list
            })
        
        # Apply where filters if any exist
        if where_filters:
            if len(where_filters) == 1:
                # If only one filter, apply it directly
                query = query.with_where(where_filters[0])
            else:
                # If multiple filters, combine with "And" operator
                query = query.with_where({
                    "operator": "And",
                    "operands": where_filters
                })
    
        result = query.do()
        output = result['data']['Get'][class_id_kb]

        for doc in output:
            doc['doc_id'] = doc['_additional']['id']
            doc['similarity'] = doc['_additional']['certainty']
            doc.pop('_additional')

        return jsonify({'data': output, 'result': 'success'})

    except Exception as e:
        return jsonify({'data': f'error occured! please try again{e}', 'result': 'failed' }), 404

def extract_field_values_with_azure(message, fields):
    """
    Extract values for the specified fields from the message using Azure OpenAI.
    
    Args:
        message (str): User message
        fields (list): List of fields to extract
    
    Returns:
        str: Processed message with extracted field values
    """
    try:
        FUNCTION_EXTRACT = [
            {
            "name": "extract_metadata",
            "description": "Use this function to extract the required metadata from the user's questions, the metadata is the information that will be used to filter the data from the knowledge base",
            "parameters": {
                "type": "object",
                "properties": {
                    "condition": {
                        "type": "array",
                        "items": {
                            "type": "string"
                            },
                        "description": "condition is the information that will using method 'CONTAINS ANY' to filter the data. so it's should be a list of one word keyword. Keyword must be in English, specific yet common to be easy to filter the data. Avoid to use asking, question, inquiries, and etc as a condition keyword since user always asking",
                        },
                    "specific_time": {
                        "type": "array",
                        "items": {
                            "type": "string"
                            },
                        "description": "specific_time is the information regarding the time and will used method 'CONTAINS ALL' so there's no tolerance of error, the datetime must be separate with comma (array) and only contains year with month with format YYYY-MM. If no specific time is given, return empty array",
                        }
                    },
                "required": ["condition", "specific_time"],
                }
            }
        ]
        
        # Construct prompt for field extraction
        prompt = f"Extract the following information from this Text: {message}"
        
        # Call Azure OpenAI
        response = client_azure.chat.completions.create(
            model="gpt-4o",  # Replace with your deployed model name
            messages=[
                {"role": "system", "content": "You are an AI assistant that extracts specific fields from text. There are 2 arguments need to be extracted. 'condition' is contained a clear, distinct keywords that representation of user needs. 'specific_time' is a list of datetime to minimalize the range of filtered data if the user asking for a specific time. You must apply and adhere the datetime format and do not force to get the datetime data if the user doesn't give the required time."},
                {"role": "user", "content": prompt}
            ],
            temperature=0.0,  # Low temperature for more deterministic responses
            top_p=0.95,
            functions=FUNCTION_EXTRACT,
            function_call={"name": "extract_metadata"}
        )
        
        # Get the extracted values
        extracted_text = response.choices[0].message.function_call.arguments.strip()

        
        return extracted_text
    except Exception as e:
        # If extraction fails, fall back to original message
        print(f"Error in Azure OpenAI extraction: {e}")
        return message
