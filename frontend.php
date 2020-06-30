<?php
    // Frontend php site

    // Shortcode for blancket
    frontend_update();
    function frontend_update(){
        add_shortcode('HTX_Tilmeldningsblanket','HTX_lan_tilmdeldingsblanket_function');
    }
    
    //perform the shortcode output
    function HTX_lan_tilmdeldingsblanket_function(){
        // Custom connection to database
        $link = database_connection();
        global $wpdb;
        
        // add to $html, to return it at the end -> It is how to do shortcodes in Wordpress
        $html = "";
        $tableId = 1;

        // Standard load
        // $html .= HTX_load_standard_frontend();

        // Getting and writing form name
        $table_name = $wpdb->prefix . 'htx_form_tables';
        $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE tableid = ?");
        $stmt->bind_param("i", $tableId);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows === 0) {return HTX_frontend_sql_notworking();} else {
            while($row = $result->fetch_assoc()) {
                $formName = $row['tableName'];
            }
            $stmt->close();
        }
        $html .= "<h2>$formName</h2>";
        
        // Getting and writing content to form
        // Getting column info
        $table_name = $wpdb->prefix . 'htx_column';
        $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE tableid = ? AND adminOnly = 0");
        $stmt->bind_param("i", $tableId);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows === 0) {return HTX_frontend_sql_notworking();} else {
            while($row = $result->fetch_assoc()) {
                $columnNameFront[] = $row['columnNameFront'];
                $columnNameBack[] = $row['columnNameBack'];
                $format[] = $row['format'];
                $columnType[] = $row['columnType'];
                $special[] = $row['special'];
                $specialName[] = $row['specialName'];
                $placeholderText[] = $row['placeholderText'];
                $sorting[] = $row['sorting'];
                $adminOnly[] = $row['adminOnly'];
            }
            $stmt->close();
        }
        // Setting up form
        $html .= "<form action=".htmlspecialchars($_SERVER["PHP_SELF"])." method=\"post\">";

        // Writing for every column entry
        for ($i=0; $i < count($columnNameFront); $i++) { 
            $html .= "<p><label>$columnNameFront[$i]</label>";
            switch ($columnType[$i]) {
                case "dropdown":
                    // Getting settings category
                    $table_name = $wpdb->prefix . 'htx_settings_cat';
                    $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE tableId = ? AND settingNameBack = ? LIMIT 1");
                    $stmt->bind_param("is", $tableId, $columnNameBack[$i]);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if($result->num_rows === 0)  {return HTX_frontend_sql_notworking();} else {
                        while($row = $result->fetch_assoc()) {
                            $setting_cat_settingId = $row['id'];
                            $setting_cat_settingName = $row['settingName'];
                            $setting_cat_settingType = $row['settingType'];
                        }
                    }
                    $stmt->close();
                    // Writing first part of dropdown
                    $html .= "<select name='$columnNameFront[$i]'>";
                    // Getting dropdown content
                    
                    $table_name = $wpdb->prefix . 'htx_settings';
                    $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE settingId = ? ORDER BY sorting");
                    $stmt->bind_param("i", $setting_cat_settingId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if($result->num_rows === 0)  {return HTX_frontend_sql_notworking();} else {
                        while($row = $result->fetch_assoc()) {
                            // Getting data
                            $setting_settingName = $row['settingName'];
                            $setting_value = $row['value'];

                            // Write data
                            $html .= "<option name='$setting_settingName' value='$setting_value'>".$setting_settingName."</option>";
                        }
                    }
                    $stmt->close();

                    // Finishing dropdown
                    $html .= "</select>";
                break;
                default: $html .= "<input name='$columnNameFront[$i]' type='$format[$i]' placeholder='$placeholderText[$i]' class='inputBox'></p>";
            }
            
        }

        // Ending form with submit and reset buttons
        $html .= "<p><button type='submit' name='submit'>Tilmeld</button> <button type='reset' name='reset'>Nulstil</button></p>";
        // Returning code
        return $html;
    }
?>