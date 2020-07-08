<?php
    // Tabel med alle tilmeldinger som kan ses - Evt en knap som kan trykkes, hvor så at felterne kan blive redigerbare - Man kan vælge imellem forskellige forms
    // Widgets and style
    HTX_load_standard_backend();

    // Getting start information for database connection
    global $wpdb;
    // Connecting to database, with custom variable
    $link = database_connection();

    // Header
    echo "<h1>HTX Lan tilmeldinger</h1>";

    // Getting data about forms
    $table_name = $wpdb->prefix . 'htx_form_tables';
    $stmt = $link->prepare("SELECT * FROM `$table_name` where active = 1 ORDER BY favorit DESC, tableName ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows === 0) echo "Ingen formularer"; else {
        while($row = $result->fetch_assoc()) {
            $tableIds[] = $row['id'];
            $tableNames[] = $row['tableName'];
        }

        // Getting table id
        if (in_array(intval($_GET['formular']), $tableIds)) $tableId = intval($_GET['formular']); else $tableId = $tableIds[0];

        // Post handling
        participantList_post($tableId);

        // Dropdown menu
        // Starting dropdown menu
        echo "<p><h3>Formular:</h3> ";
        echo "<form method=\"get\"><input type='hidden' name='page' value='HTX_lan_participants_list'><select name='formular' class='dropdown' onchange='form.submit()'>";
        // writing every option
        for ($i=0; $i < count($tableIds); $i++) {
            // Seeing if value is the choosen one
            if ($tableIds[$i] == $tableId) $isSelected = "selected"; else $isSelected = "";

            // Writing value
            echo "<option value='$tableIds[$i]' $isSelected>$tableNames[$i]</option>";
        }

        // Ending dropdown
        echo "</select></form><br></p>";

        // Start of table with head
        echo "<div class='formGroup_container'><div class='formGroup formGroup_scroll_left'><table class='InfoTable'><thead><tr>";

        // Getting information from database
        // Users
        $table_name = $wpdb->prefix . 'htx_form_users';
        $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE tableId = ? AND active = 1");
        $stmt->bind_param("i", $tableId);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows === 0) echo "Ingen registreringer"; else {
            // Getting every column
            $table_name3 = $wpdb->prefix . 'htx_column';
            $stmt3 = $link->prepare("SELECT * FROM `$table_name3` WHERE tableid = ?");
            $stmt3->bind_param("i", $tableId);
            $stmt3->execute();
            $result3 = $stmt3->get_result();
            if($result3->num_rows === 0) {return HTX_frontend_sql_notworking();} else {
                while($row3 = $result3->fetch_assoc()) {
                    $columnNameFront[] = $row3['columnNameFront'];
                    $columnNameBack[] = $row3['columnNameBack'];
                    $format[] = $row3['format'];
                    $columnType[] = $row3['columnType'];
                    $special[] = $row3['special'];
                    $specialName[] = explode(",", $row3['specialName']);
                    $placeholderText[] = $row3['placeholderText'];
                    $sorting[] = $row3['sorting'];
                    $required[] = $row3['required'];
                }
            }
            $stmt3->close();

            // Non user editable inputs saved
            $nonUserInput = array('text area');

            // Pre main column
            echo "<th></th>";

            // Writing every column and insert into table head
            for ($i=0; $i < count($columnNameBack); $i++) {
                // Check if input should not be shown
                if (!in_array($columnType[$i], $nonUserInput)) {
                    echo "<th>$columnNameFront[$i]</th>";
                }
            }
            // Writing extra lines
            echo "<th>Betaling</th>";
            echo "<th><span class='material-icons' title='Ankommet' style='cursor: help'>flight_land</span></th>";
            echo "<th><span class='material-icons' title='Person er en del af crew' style='cursor: help'>people_alt</span></th>";
            echo "<th>Pris</th>";
            echo "<th></th>";

            // Ending head
            echo "</tr></head>";

            // User information
            // Stating table body
            echo "<tbody>";

            // Getting every user ids
            while($row = $result->fetch_assoc()) {
                $userid[] = $row['id'];
                $payed[] = $row['payed'];
                $arrived[] = $row['arrived'];
                $crew[] = $row['crew'];
                $prices[] = $row['price'];
            }

            // Getting every dropdown and radio categories
            $table_name3 = $wpdb->prefix . 'htx_settings_cat';
            $stmt3 = $link->prepare("SELECT * FROM `$table_name3` WHERE tableId = ? AND active = 1");
            $stmt3->bind_param("i", $tableId);
            $stmt3->execute();
            $result3 = $stmt3->get_result();
            if($result3->num_rows === 0) echo ""; else {
                while($row3 = $result3->fetch_assoc()) {
                    $settingNameBacks[] = $row3['settingNameBack'];
                    $settingType[] = $row3['settingType'];
                }
            }
            // Getting every dropdown and radio names and values
            $table_name3 = $wpdb->prefix . 'htx_settings';
            $stmt3 = $link->prepare("SELECT * FROM `$table_name3` WHERE active = 1");
            $stmt3->execute();
            $result3 = $stmt3->get_result();
            if($result3->num_rows === 0) echo ""; else {
                while($row3 = $result3->fetch_assoc()) {
                    $settingName[$row3['id']] = $row3['settingName'];
                    $settingValue[$row3['id']] = $row3['value'];
                }
            }


            // Getting and writing every user information
            for ($i=0; $i < count($userid); $i++) {
                // Starting price
                $price = 0;
                $priceExtra = 0;

                echo "<tr class='InfoTableRow'>";
                echo "<td><span class='material-icons' style='cursor: pointer'>edit</span></td>";
                // For every column
                for ($index=0; $index < count($columnNameBack); $index++) {
                    // Getting data for specefied column
                    $table_name2 = $wpdb->prefix . 'htx_form';
                    $stmt2 = $link->prepare("SELECT * FROM `$table_name2` WHERE tableid = ? AND userId = ? AND name = ?");
                    $stmt2->bind_param("iis", $tableId, $userid[$i], $columnNameBack[$index]);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    if($result2->num_rows === 0) {
                        if (!in_array($columnType[$index], $nonUserInput)) {
                            echo "<i style='color: red'>Null</i>";
                        }
                    }else {
                        echo "<td>";
                        while($row2 = $result2->fetch_assoc()) {
                            // Checks if dropdown or other where value is an id
                            if (in_array($row2['name'], $settingNameBacks)) {
                                // Writing data from id, if dropdown or radio
                                if ($columnType[$index] == "checkbox") {
                                    $valueArray = explode(",", $row2['value']);
                                    if (count($valueArray) > 0) {
                                        for ($j=0; $j < count($valueArray); $j++) { 
                                            echo htmlspecialchars($settingName[$valueArray[$j]]);
    
                                            // Writing price
                                            if (in_array('price_intrance', $specialName[$index])) {
                                                $price = $price + floatval($settingValue[$valueArray[$j]]);
        
                                            }
                                            // Writing extra price
                                            if (in_array('price_extra', $specialName[$index])) {
                                                $priceExtra = $priceExtra + floatval($settingValue[$valueArray[$j]]);
                                            }
                                            // Insert comma, if the value is not the last
                                            if (count($valueArray) != ($j + 1)) {
                                                echo ", ";
                                            }
                                        }
                                    }
                                } else {
                                    echo htmlspecialchars($settingName[$row2['value']]);

                                    // Writing price
                                    if (in_array('price_intrance', $specialName[$index])) {
                                        $price = $price + floatval($settingValue[$row2['value']]);

                                    }
                                    // Writing extra price
                                    if (in_array('price_extra', $specialName[$index])) {
                                        $priceExtra = $priceExtra + floatval($settingValue[$row2['value']]);
                                    }
                                    echo "check5";
                                }
                                
                            } else {
                                // Checks column type
                                if (!in_array($columnType[$index], $nonUserInput)) {
                                    // Writing data from table
                                    echo htmlspecialchars($row2['value']);
                                    // Writing price
                                    if (in_array('price_intrance', $specialName[$index])) {
                                        $price = $price + floatval($row2['value']);
                                    }
                                    if (in_array('price_intrance', $specialName[$index])) {
                                        $priceExtra = $priceExtra + floatval($row2['value']);
                                    }
                                }
                            }
                        }
                        echo "</td>";
                    }
                    $stmt2->close();
                }
                // Adding payed, and arrived at the end of inputs
                // Payed

                // Getting different payed option - These are pre determined, such as cash, mobilepay
                $paymentMethods = array("Kontant", "Mobilepay");
                $paymentMethodsId = array("0", "0-f", "1-f");

                echo "<td ";
                if ($payed[$i] == "0") echo "class='unpayed'";
                else if ($payed[$i] == "0-i" OR $payed[$i] == "1-i") echo "class='crewpayed'";
                else if (in_array($payed[$i], $paymentMethodsId)) echo "class='payed'";
                echo ">
                    <form id='$i-pay' method='POST'>
                    <input type='hidden' name='userId' value='$userid[$i]'>
                    <input type='hidden' name='post' value='paymentUpdate'>
                    <select name='paymentOption'  onchange='document.getElementById(\"$i-pay\").submit()'>
                        <option value='0'";
                    if ($payed[$i] == 0) echo "selected";
                        echo">Ingen</option>";
                for ($j=0; $j < count($paymentMethods); $j++) {
                    echo "<option value='$j-f'";
                    if ($payed[$i] == "$j-f") echo "selected";
                    echo">$paymentMethods[$j]</option>";
                }
                echo "</select>
                    </form>
                </td>";

                // Arrived
                echo "<td style='text-align: center'>";
                echo "<form id='$i-arrived' method='POST'>";
                echo "<input type='hidden' name='post' value='arrivedtUpdate'><input type='hidden' name='userId' value='$userid[$i]'>";
                echo "<input type='hidden' name='arrived' value='0'>";
                echo "<input id='arrived-$i' type='checkbox' class='inputCheckbox' name='arrived' value='1' onchange='document.getElementById(\"$i-arrived\").submit()'";
                if ($arrived[$i] == 1) echo "checked";
                echo ">";
                echo "</form>";
                echo "</td>";

                // Crew
                echo "<td style='text-align: center'>";
                echo "<form id='$i-crew' method='POST'>";
                echo "<input type='hidden' name='post' value='crewUpdate'><input type='hidden' name='userId' value='$userid[$i]'>";
                echo "<input type='hidden' name='crew' value='0'>";
                echo "<input id='crew-$i' type='checkbox' class='inputCheckbox' name='crew' value='1' onchange='document.getElementById(\"$i-crew\").submit()'";
                if ($crew[$i] == 1) {echo "checked"; $price = 0;}
                echo ">";
                echo "</form>";
                echo "</td>";

                // Price
                $price = floatval($price) + floatval($priceExtra);
                echo "<td>$price,-</td>";
                // Updating price, if it does not match with what is in the database
                if ($price != $prices[$i]) {
                    $table_name2 = $wpdb->prefix . 'htx_form_users';
                    $stmt2 = $link->prepare("SELECT * FROM `$table_name2` WHERE id = ?");
                    $stmt2->bind_param("i", $userid[$i]);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    if($result2->num_rows === 0) echo "<scrip>location.reload();</script>" /* User does not exist */; else {
                        while($row2 = $result2->fetch_assoc()) {
                            $table_name4 = $wpdb->prefix . 'htx_form_users';
                            $stmt4 = $link->prepare("UPDATE $table_name4 SET price = ? WHERE id = ?");
                            $stmt4->bind_param("si", $price, $userid[$i]);
                            $stmt4->execute();
                            $stmt4->close();
                        }
                    }
                    $stmt2->close();
                }


                // Delete
                echo "<td>
                <a>
                    <form name='deleteForm-$i' id='deleteForm-$i' method='POST'>
                        <input type='hidden' name='userid' value='$userid[$i]'>
                        <input type='hidden' name='delete' value='deleteSubmission'>
                        <span class='material-icons' style='cursor: pointer' onclick='confirmDelete(\"deleteForm-$i\")'>delete_forever</span>
                    </form>
                </a>
                </td>";
            }

        }
        $stmt->close();

        // Ending table
        echo "</thead></table></div>";
    }
?>
