<?php

    // --------------------   define db connection of mysql.  --------- //

    function getdb(){
        $servername = "localhost";
        $username = "root";
        $password = "";
        $db = "daily_agency_report";
        try {
        
            $con = mysqli_connect($servername, $username, $password, $db);
            echo "Connected successfully"; 
        }
        catch(exception $e)
        {
            echo "Connection failed: " . $e->getMessage();
        }
        return $con;
    }

    // ----------------------------------------------------------------- //

    $csv_filenames = array();

    if (! function_exists('imap_open')) {
        echo "IMAP is not configured.";
        exit();
    } 
    else {
        $username = ''; // mail user name
        $password = '';         // mail password
        $server = '{imap-mail.outlook.com:993/ssl}INBOX'; // mail server name {imap.gmail.com:993/ssl}INBOX

		/* Connecting Gmail server with IMAP */
		
        $connection = imap_open($server, $username, $password) or die('Cannot connect to Gmail: ' . imap_last_error());
        $date = date_create();
        $date = date_format($date,"d-M-Y");
        //echo $date;
        /* Search Emails having the specified keyword in the email subject */
        $emailData = imap_search($connection, 'SINCE "'.$date.'"');
        //var_dump($emailData);
        if (! empty($emailData)) {
            foreach ($emailData as $key => $emailIdent) {

                $structure = imap_fetchstructure($connection, $emailIdent);

                $attachments = array();
                if(isset($structure->parts) && count($structure->parts)) 
                {
                    for($i = 0; $i < count($structure->parts); $i++) 
                    {
                        $attachments[$i] = array(
                            'is_attachment' => false,
                            'filename' => '',
                            'name' => '',
                            'attachment' => ''
                        );

                        if($structure->parts[$i]->ifdparameters) 
                        {
                            foreach($structure->parts[$i]->dparameters as $object) 
                            {
                                if(strtolower($object->attribute) == 'filename') 
                                {
                                    $attachments[$i]['is_attachment'] = true;
                                    $attachments[$i]['filename'] = $object->value;
                                }
                            }
                        }

                        if($structure->parts[$i]->ifparameters) 
                        {
                            foreach($structure->parts[$i]->parameters as $object) 
                            {
                                if(strtolower($object->attribute) == 'name') 
                                {
                                    $attachments[$i]['is_attachment'] = true;
                                    $attachments[$i]['name'] = $object->value;
                                }
                            }
                        }

                        if($attachments[$i]['is_attachment']) 
                        {
                            $attachments[$i]['attachment'] = imap_fetchbody($connection, $emailIdent, $i+1);

                            /* 3 = BASE64 encoding */
                            if($structure->parts[$i]->encoding == 3) 
                            { 
                                $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                            }
                            /* 4 = QUOTED-PRINTABLE encoding */
                            elseif($structure->parts[$i]->encoding == 4) 
                            { 
                                $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                            }
                        }
                    }
                }

                /* iterate through each attachment and save it */
                $current_date = date("Y-m-d");
                //$current_date = "2019-03-31";
                
                foreach($attachments as $attachment)
                {
                    if($attachment['is_attachment'] == 1)
                    {
                        
                        $filename = $attachment['name'];
                        if(strpos( $filename, $current_date ) !== false){
                            
                            if(empty($filename)) $filename = $attachment['filename'];

                            if(empty($filename)) $filename = time() . ".dat";
                            $folder = "attachment";
                            if(!is_dir($folder))
                            {
                                mkdir($folder);
                            }
                            $fp = fopen("./". $folder ."/". $emailIdent . "-" . $filename, "w+");
                            array_push($csv_filenames, $emailIdent . "-" . $filename);
                            fwrite($fp, $attachment['attachment']);
                            fclose($fp);
                        }
                           
                    }
                }
            }
        } // end if
        
        imap_close($connection);
    }
    //var_dump($csv_filenames);

    $con = getdb();

    foreach( $csv_filenames as $key => $csv_filename ){
        $filename = 'attachment/'.$csv_filename;
        
        if ( !file_exists($filename) ) {
            throw new Exception('File not found.');
        }
        //echo "file can be found";
        $file = fopen($filename, "r");
        while (($getData = fgetcsv($file, 10000, ",")) !== FALSE)
        {
            // var_dump($getData);
            if($getData[0] === ""){
                continue;
            }
            $sql = 'SELECT count(*) AS c FROM daily_agency_report WHERE event_date = "'.$getData[1] .'" && client = "'.$getData[2].'" && client_channel = "'.$getData[3].'" && revenue_source = "'.$getData[4].'" && country_name = "'.$getData[5].'" && platform = "'.$getData[6].'" && tq_score = '.$getData[7];
            //echo $sql;
            $result = mysqli_query($con, $sql);
            //var_dump($result);
            if($result == false){
                $sql = "INSERT into daily_agency_report (event_date, client, client_channel, revenue_source, country_name, platform, tq_score, queries, matched_queries,clicks, impressions, page_rpm, impression_rpm, revenue) 
                    VALUES ('".$getData[1]."','".$getData[2]."','".$getData[3]."','".$getData[4]."','".$getData[5]."','".$getData[6]."',".(int)$getData[7].",".(int)$getData[8].",".(int)$getData[9].",".(int)$getData[10].",".(int)$getData[11].",".(float)$getData[12].",".(float)$getData[13].",'".$getData[14]."');";
            }
            else{
                $row = mysqli_fetch_assoc($result);
                //var_dump($row["c"]);

                if( $row["c"] !== "0"){
                    $sql = 'UPDATE daily_agency_report SET tq_score = '.(int)$getData[7].', queries = '.(int)$getData[8].', matched_queries = '.(int)$getData[9].',clicks = '.(int)$getData[10].', impressions = '.(int)$getData[11].', page_rpm = '.(float)$getData[12].', impression_rpm = '.(float)$getData[13].', revenue = "'.$getData[14].
                            '" WHERE event_date = "'.$getData[1] .'" && client = "'.$getData[2].'" && client_channel = "'.$getData[3].'" && revenue_source = "'.$getData[4].'" && country_name = "'.$getData[5].'" && platform = "'.$getData[6].'"';
                }
                else{
                    $sql = "INSERT into daily_agency_report (event_date, client, client_channel, revenue_source, country_name, platform, tq_score, queries, matched_queries,clicks, impressions, page_rpm, impression_rpm, revenue) 
                        VALUES ('".$getData[1]."','".$getData[2]."','".$getData[3]."','".$getData[4]."','".$getData[5]."','".$getData[6]."',".(int)$getData[7].",".(int)$getData[8].",".(int)$getData[9].",".(int)$getData[10].",".(int)$getData[11].",".(float)$getData[12].",".(float)$getData[13].",'".$getData[14]."');";
                }
            }
            
            //echo $sql;
            $result = mysqli_query($con, $sql);
            if(!isset($result))
            {
                echo "<script type=\"text/javascript\">
                alert(\"Invalid File.\");
                </script>";    
                break;
            }
        }
      
        fclose($file);  
    }
?>