<?php
    //Prevent direct file access
    if(!defined('ABSPATH')) {
        header("Location: ../../../../");
        die();
    }

    // Tabel med alle tilmeldinger som kan ses - Evt en knap som kan trykkes, hvor så at felterne kan blive redigerbare - Man kan vælge imellem forskellige forms
    // Widgets and style
    HTX_load_standard_backend();
    wp_enqueue_script( 'participantList_script', "/wp-content/plugins/wp-htxlan/code/JS/participant.js");
    wp_enqueue_style( 'frontendForm', "/wp-content/plugins/wp-htxlan/code/CSS/form-participant.css");

    // Getting start information for database connection
    global $wpdb;
    // Connecting to database, with custom variable
    $link = database_connection();

    // Non user editable inputs saved
    $nonUserInput = array('text area', 'price','spacing');

    // Price handling array
    $possiblePriceFunctions = array("price_intrance", "price_extra");
    $priceSet = false;

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
            $tableArrived[$row['id']] = $row['arrived'];
            $tableArrivedAtDoor[$row['id']] = $row['arrivedAtDoor'];
            $tableCrew[$row['id']] = $row['crew'];
            $tablePizza[$row['id']] = $row['pizza'];
        }

        // Getting table id
        if (isset($_GET['formular'])) {
            if (in_array(intval($_GET['formular']), $tableIds)) $tableId = intval($_GET['formular']); else $tableId = $tableIds[0];

            // Check cookie
            if(!isset($_COOKIE['tableId'])) {
                // Set cookie because it does not exist
                setCustomCookie('tableId',$tableId);
            } else {
                // Cookie exist
                if (intval($_COOKIE['tableId']) != $tableId) 
                setCustomCookie('tableId',$tableId); // Cookie does not match formular - Updatet cookie
            }
        } else {
            // Check cookie
            if(!isset($_COOKIE['tableId'])) {
                // Set cookie because it does not exist
                $tableId = $tableIds[0]; //Use first table
                setCustomCookie('tableId',$tableId);
            } else {
                // Cookie exist
                if (in_array(intval($_COOKIE['tableId']), $tableIds)) 
                    // Cookie is a valid table - Set as new table
                    $tableId = intval($_COOKIE['tableId']); 
                else {
                    // Cookie is not a valid cookie, set standard
                    $tableId = $tableIds[0]; //Use first table
                    setCustomCookie('tableId',$tableId);
                }
            }
        }

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

        // Posthandling for user edit area
        $postError = HTX_participant_edit_post($tableId);

        // Possible to edit what to show on this page
        echo "<button class='btn normalBtn' style='margin-bottom: 1rem;' onclick='showTeamColumnSettings()'>Ændre viste felter</button><br>";

        // Get already choosen elements - If no elements are present use default
        $userId = get_current_user_id();

        // Gets all columns
        $table_name = $wpdb->prefix . 'htx_column';
        $stmt = $link->prepare("SELECT * FROM `$table_name` where tableId = ?");
        $stmt->bind_param("i", $tableId);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows === 0) {
            $stmt->close();
            $columns = array();
        } else {
            while($row = $result->fetch_assoc()) {
                $columns[] = $row['id'];
            }
        }

        // Getting user preferrence
        $table_name = $wpdb->prefix . 'htx_settings';
        $stmt = $link->prepare("SELECT * FROM `$table_name` where settingName = ? AND NOT value = '' AND active = 1 AND type = 'participantUserPreference' AND tableId = ? LIMIT 1");
        $stmt->bind_param("ii", $userId, $tableId);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows === 0) {
            $stmt->close();
            $headsShown = array();
            $userSetting = false;
        } else {
            while($row = $result->fetch_assoc()) {
                $headsShown = explode(",", $row['value']);
                if (count(array_intersect($columns, $headsShown)) != count($headsShown)) $headsShown = array();
            }
            $userSetting = true;
        }

        // Post handling
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            switch  ($_POST['post']) {
                case 'updateUserPreference':
                    if(!current_user_can("manage_options")){
                        echo "User can not do that!";
                        break;
                    }
                $tempArray = array();
                $tempSrting = "";
                for ($i=0; $i < count($_POST['shownColumns']); $i++) { 
                    if (in_array($_POST['shownColumns'][$i], $columns)) {
                        $tempArray[] = $_POST['shownColumns'][$i];
                    }
                }
                if (count(array_intersect($columns, $tempArray)) == count($tempArray)) {
                    $headsShown = $tempArray;
                    $headsShownString = implode(",",$headsShown);

                    // Update database
                    if ($userSetting == false) {
                        // Make new record in database
                        $table_name = $wpdb->prefix . 'htx_settings';
                        $stmt = $link->prepare("INSERT INTO `$table_name` (settingName, value, type, tableId) VALUES (?, ?, 'participantUserPreference', ?)");
                        $stmt->bind_param("isi", $userId, $headsShownString, $tableId);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        // Update record
                        $table_name = $wpdb->prefix . 'htx_settings';
                        $stmt = $link->prepare("UPDATE `$table_name` SET value = ? WHERE settingName = ? and tableId = ?");
                        $stmt->bind_param("sii", $headsShownString, $userId, $tableId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                break;
            }
        }

        // Page for showing possible columns to show
        wp_enqueue_style( 'teams_style', "/wp-content/plugins/wp-htxlan/code/CSS/teams.css");
        wp_enqueue_script( 'teams_script', "/wp-content/plugins/wp-htxlan/code/JS/teams.js");
        echo "<div id='columnShownEditPage' class='columnShownEditPage_closed'>";
        echo "<h2>Viste kolonner</h2>";
        echo "<form method='POST'>";
        // Columns:
        $table_name = $wpdb->prefix . 'htx_column';
        $stmt = $link->prepare("SELECT * FROM `$table_name` where (active = 1 and tableId = ?) AND NOT(columnType = 'text area' OR columnType = 'price' OR columnType = 'spacing')");
        $stmt->bind_param("i", $tableId);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows === 0) {
            echo "Der er ingen elementer i form";
            $stmt->close();
            $Error = true;
            die; #Ending page, becuase of error
        } else {
            while($row = $result->fetch_assoc()) {
                if (in_array($row['columnType'], $nonUserInput)) continue;
                if (in_array($row['id'], $headsShown)) $selected = 'checked="checked"'; else $selected = '';
                if ($userSetting == false) $selected = 'checked="checked"';
                echo "<input type='checkbox' id='checkBox-".$row['id']."' name='shownColumns[]' value='".$row['id']."' $selected><label for='checkBox-".$row['id']."'>".$row['columnNameFront']."</label><br>";
            }
        }
        echo "<button type='submit' class='btn updateBtn' name='post' value='updateUserPreference'>Opdater</button>";
        echo "</form>";
        echo "</div>";


        // Start of table with head
        echo "<div class='formGroup_container' style='overflow-x: hidden; margin-bottom: 1rem;'><div class='formGroup formGroup_scroll_left'>
            <table class='InfoTable' id='participantListTable'><thead><tr>";

        // Getting information from database
        // Users
        $table_name = $wpdb->prefix . 'htx_form_users';
        $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE tableId = ? AND active = 1");
        if(!$stmt)
                throw new Exception($link->error);
        $stmt->bind_param("i", $tableId);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows === 0) echo "Ingen registreringer"; else {
            // Getting every column
            $table_name3 = $wpdb->prefix . 'htx_column';
            $stmt3 = $link->prepare("SELECT * FROM `$table_name3` WHERE tableid = ? ORDER BY sorting");
            $stmt3->bind_param("i", $tableId);
            $stmt3->execute();
            $result3 = $stmt3->get_result();
            if($result3->num_rows === 0) {echo "Noget gik galt...";} else {
                while($row3 = $result3->fetch_assoc()) {
                    $columnId[] = $row3['id'];
                    $columnNameFront[] = $row3['columnNameFront'];
                    $columnNameBack[] = $row3['columnNameBack'];
                    $format[] = $row3['format'];
                    $columnType[] = $row3['columnType'];
                    $special[] = $row3['special'];
                    $specialName[] = explode(",", $row3['specialName']);
                    $placeholderText[] = $row3['placeholderText'];
                    $sorting[] = $row3['sorting'];
                    $required[] = $row3['required'];

                    $formatExtra[] = $row3['formatExtra'];
                    $specialNameExtra[] = $row3['specialNameExtra'];
                    $specialNameExtra2[] = explode(",", $row3['specialNameExtra2']);
                    $specialNameExtra3[] = $row3['specialNameExtra3'];
                    $disabled[] = $row3['disabled'];
                    $settingCat[] = $row3['settingCat'];

                    $columnNameFrontID[$row3['id']] = $row3['columnNameFront'];
                    $columnNameBackID[$row3['id']] = $row3['columnNameBack'];
                    $formatID[$row3['id']] = $row3['format'];
                    $formatExtraID[$row3['id']] = $row3['formatExtra'];
                    $columnTypeID[$row3['id']] = $row3['columnType'];
                    $specialID[$row3['id']] = $row3['special'];
                    $specialNameID[$row3['id']] = explode(",", $row3['specialName']);
                    $specialNameExtraID[$row3['id']] = $row3['specialNameExtra'];
                    $specialNameExtra2ID[$row3['id']] = explode(",", $row3['specialNameExtra2']);
                    $specialNameExtra3ID[$row3['id']] = $row3['specialNameExtra3'];
                    $placeholderTextID[$row3['id']] = $row3['placeholderText'];
                    $sortingID[$row3['id']] = $row3['sorting'];
                    $disabledID[$row3['id']] = $row3['disabled'];
                    $requiredID[$row3['id']] = $row3['required'];
                    $settingCatID[$row3['id']] = $row3['settingCat'];
                }
            }
            $stmt3->close();

            // Pre main column
            echo "<th></th>";
            echo "<th onClick='sortTable(1,1,\"participantListTable\",true,\"participantListTable\")' title='Sorter efter denne kolonne' style='cursor: pointer' class='table_header'>
            <span>Id</span>
            <span class='material-icons arrowInline sortingCell_participantListTable' id='sortingSymbol_participantListTable_1'></span></th>";

            // Writing every column and insert into table head
            $columnNumber = 2;
            for ($i=0; $i < count($columnNameBack); $i++) {
                // Check if input should not be shown
                if (!in_array($columnType[$i], $nonUserInput)) {
                    if (!in_array($columnId[$i], $headsShown) AND $userSetting == true) $shown[$i] = "hidden"; else $shown[$i] = "";
                    echo "<th onClick='sortTable(1,$columnNumber,\"participantListTable\",true,\"participantListTable\")' class='table_header $shown[$i]' title='Sorter efter denne kolonne' style='cursor: pointer'>
                        <span class='table_header_text'>$columnNameFront[$i]</span>&nbsp;
                        <span class='material-icons arrowInline sortingCell_participantListTable' id='sortingSymbol_participantListTable_$columnNumber'></span>
                    </th>";
                    $columnNumber++;
                }
            }
            // Writing extra lines
            echo "<th>Betaling</th>";
            if ($tableArrived[$tableId] == 1)
                $cellVisibility = '';
            else 
                $cellVisibility = 'hidden';
                echo "<th class='$cellVisibility'><span class='material-icons' title='Ankommet' style='cursor: help'>flight_land</span></th>";
            if ($tableCrew[$tableId] == 1)
            $cellVisibility = '';
            else 
                $cellVisibility = 'hidden';
                echo "<th class='$cellVisibility'><span class='material-icons' title='Person er en del af crew' style='cursor: help'>people_alt</span></th>";
            if ($tablePizza[$tableId] == 1)
                $cellVisibility = '';
            else 
                $cellVisibility = 'hidden';
                echo "<th class='$cellVisibility'><span class='material-icons' title='Person har fået leveret pizza' style='cursor: help'>local_pizza</span></th>";
            if ($tableArrivedAtDoor[$tableId] == 1)
                $cellVisibility = '';
            else 
                $cellVisibility = 'hidden';
            echo "<th class='$cellVisibility'><span class='material-icons' title='Person købte billet ved ankomst' style='cursor: help'>sensor_door</span></th>";
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
                $arrivedAtDoor[] = $row['arrivedAtDoor'];
                $crew[] = $row['crew'];
                $pizza[] = $row['pizza'];
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
                $settingName[0] = "";
                $settingNameID[0] = "";
                $settingValue[0] = "";
                while($row3 = $result3->fetch_assoc()) {
                    $settingName[$row3['id']] = $row3['settingName'];
                    $settingNameID[$row3['id']] = $row3['id'];
                    $settingValue[$row3['id']] = $row3['value'];
                }
            }

            // download data array
            $dataForDownload = array();

            // Getting and writing every user information
            for ($i=0; $i < count($userid); $i++) {
                // Data for line
                $lineData = array();
                // Starting price
                $price = 0;
                $priceExtra = 0;

                echo "<form method='GET' id='openEdit-$userid[$i]'><tr class='InfoTableRow'>";
                echo "<td onclick='document.forms[\"openEdit-$userid[$i]\"].submit();'><span class='material-icons' style='cursor: pointer'>edit</span></td>";
                echo "<input type='hidden' value='".$_GET['page']."' name='page'>";
                echo "<input type='hidden' value='$userid[$i]' name='editUser'>";
                echo "<td>$userid[$i]</td></form>";

                // data
                $lineData['id'] = intval($userid[$i]);

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
                            echo "<td class='$shown[$index]'>";
                            echo "<i style='color: red'>-</i>";
                            echo "</td>";

                            $userData[$userid[$i]][$columnNameBack[$index]] = $row2['value'];

                            // Data for download
                            $lineData[$columnNameFront[$index]] = '-';
                        }
                    } else {
                        if (!in_array($columnType[$index], $nonUserInput)) {
                            echo "<td class='$shown[$index]'>";
                            while($row2 = $result2->fetch_assoc()) {
                                // Checks if dropdown or other where value is an id
                                if (in_array($row2['name'], $settingNameBacks)) {
                                    // Writing data from id, if dropdown or radio
                                    if ($columnType[$index] == "checkbox") {
                                        $valueArray = explode(",", $row2['value']);
                                        if (count($valueArray) > 0) {
                                            // Data
                                            $lineData[$columnNameFront[$index]] = '';
                                            for ($j=0; $j < count($valueArray); $j++) {
                                                // Writing price
                                                if (in_array('price_intrance', $specialName[$index])) {
                                                    if ($settingValue[$valueArray[$j]] != "") {
                                                        $price = $price + floatval($settingValue[$valueArray[$j]]);
                                                        echo htmlspecialchars($settingName[$valueArray[$j]]);

                                                        // Data
                                                        $lineData[$columnNameFront[$index]] .= htmlspecialchars($settingName[$valueArray[$j]]);
                                                    } else {
                                                        echo htmlspecialchars($settingValue[$valueArray[$j]]);

                                                        // Data
                                                        $lineData[$columnNameFront[$index]] .= htmlspecialchars($settingValue[$valueArray[$j]]);
                                                    }

                                                } else if (in_array('price_extra', $specialName[$index])) {
                                                    // Writing extra price
                                                    if ($settingValue[$valueArray[$j]] != "") {
                                                        $priceExtra = $priceExtra + floatval($settingValue[$valueArray[$j]]);
                                                        echo htmlspecialchars($settingName[$valueArray[$j]]);

                                                        // Data
                                                        $lineData[$columnNameFront[$index]] .= htmlspecialchars($settingName[$valueArray[$j]]);
                                                    } else {
                                                        echo htmlspecialchars($settingValue[$valueArray[$j]]);

                                                        // Data
                                                        $lineData[$columnNameFront[$index]] .= htmlspecialchars($settingValue[$valueArray[$j]]);
                                                    }
                                                } else {
                                                    echo htmlspecialchars($settingValue[$valueArray[$j]]);

                                                    // Data
                                                    $lineData[$columnNameFront[$index]] .= htmlspecialchars($settingValue[$valueArray[$j]]);
                                                }
                                                // Insert comma, if the value is not the last
                                                if (count($valueArray) != ($j + 1)) {
                                                    echo ", ";
                                                    // Data
                                                    $lineData[$columnNameFront[$index]] .= ", ";
                                                }
                                            }
                                        }
                                    } else {
                                        if (in_array('otherInput',$specialName[$index]) and !in_array($row2['value'],$settingNameID)) {
                                            if ($row2['value'] != '0') {
                                                echo htmlspecialchars($row2['value']);
                                                // Data
                                                $lineData[$columnNameFront[$index]] .= htmlspecialchars($row2['value']);
                                            } else {
                                                // Data
                                                $lineData[$columnNameFront[$index]] .= "";
                                            }
                                        } else if (in_array('price_intrance', $specialName[$index])) {
                                            // Writing price
                                            if ($settingValue[$row2['value']] != "") {
                                                $price = $price + floatval($settingValue[$row2['value']]);
                                                echo htmlspecialchars($settingName[$row2['value']]);

                                                // Data
                                                $lineData[$columnNameFront[$index]] .= htmlspecialchars($settingName[$row2['value']]);
                                            } else {
                                                echo htmlspecialchars($settingValue[$row2['value']]);

                                                // Data
                                                $lineData[$columnNameFront[$index]] .= htmlspecialchars($settingValue[$row2['value']]);
                                            }

                                        } else if (in_array('price_extra', $specialName[$index])) {
                                            // Writing extra price
                                            if ($settingValue[$row2['value']] != "") {
                                                $priceExtra = $priceExtra + floatval($settingValue[$row2['value']]);
                                                echo htmlspecialchars($settingName[$row2['value']]);

                                                // Data
                                                $lineData[$columnNameFront[$index]] .= htmlspecialchars($settingName[$row2['value']]);
                                            } else {
                                                echo htmlspecialchars($settingValue[$row2['value']]);

                                                // Data
                                                $lineData[$columnNameFront[$index]] .= htmlspecialchars($settingValue[$row2['value']]);
                                            }
                                        } else {
                                            echo htmlspecialchars($settingValue[$row2['value']]);

                                            // Data
                                            $lineData[$columnNameFront[$index]] .= htmlspecialchars($settingValue[$row2['value']]);
                                        }
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

                                        // Data
                                        $lineData[$columnNameFront[$index]] .= htmlspecialchars($row2['value']);
                                    }
                                }
                                $userData[$userid[$i]][$row2['name']] = $row2['value'];
                            }
                            echo "</td>";
                        }
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
                    <select name='paymentOption' id='paymentOption-$i' onchange='participantUpdate(\"paymentOption\",$i,$tableId,$userid[$i])'>
                        <option value='0'";
                    if ($payed[$i] == 0) echo "selected";
                        echo">Ingen</option>";
                for ($j=0; $j < count($paymentMethods); $j++) {
                    echo "<option value='$j-f'";
                    if ($payed[$i] == "$j-f") echo "selected";
                    echo">$paymentMethods[$j]</option>";
                }
                echo "</select>
                </td>";

                // data
                if ($payed[$i] == 0) {
                    $lineData['Betaling'] = 'Ingen';
                } else if ($payed[$i] == '0-f') {
                    $lineData['Betaling'] = 'Kontant';
                } else if ($payed[$i] == '0-f') {
                    $lineData['Betaling'] = 'Mobilepay';
                } else {
                    $lineData['Betaling'] = '';
                }

                // Arrived
                if ($tableArrived[$tableId] == 1) 
                    $cellVisibility = '';
                else 
                    $cellVisibility = 'hidden';
                echo "<td style='text-align: center' class='$cellVisibility'>";
                echo "<input id='arrived-$i' type='checkbox' class='inputCheckbox' name='arrived' value='1' onchange='participantUpdate(\"arrivedtUpdate\",$i,$tableId,$userid[$i])'";
                if ($arrived[$i] == 1) echo "checked";
                echo ">";
                echo "</td>";

                // data
                if ($arrived[$i] == 1)
                    $lineData['Ankommet'] = 'Ja';
                else 
                    $lineData['Ankommet'] = 'Nej';

                // Crew
                if ($tableCrew[$tableId] == 1) 
                    $cellVisibility = '';
                else 
                    $cellVisibility = 'hidden';
                echo "<td style='text-align: center' class='$cellVisibility'>";
                echo "<input id='crew-$i' type='checkbox' class='inputCheckbox' name='crew' value='1' onchange='participantUpdate(\"crewUpdate\",$i,$tableId,$userid[$i])'";
                if ($crew[$i] == 1) {echo "checked"; $price = 0;}
                echo ">";
                echo "</td>";

                // data
                if ($crew[$i] == 1)
                    $lineData['Crew'] = 'Ja';
                else 
                    $lineData['Crew'] = 'Nej';

                // Pizza leveret
                if ($tablePizza[$tableId] == 1) 
                    $cellVisibility = '';
                else 
                    $cellVisibility = 'hidden';
                echo "<td style='text-align: center' class='$cellVisibility'>";
                echo "<input id='pizza-$i' type='checkbox' class='inputCheckbox' name='pizza' value='1' onchange='participantUpdate(\"pizzaUpdate\",$i,$tableId,$userid[$i])'";
                if ($pizza[$i] == 1) echo "checked";
                echo ">";
                echo "</td>";

                // data
                if ($pizza[$i] == 1)
                    $lineData['Pizza'] = 'Ja';
                else 
                    $lineData['Pizza'] = 'Nej';

                // arrivedAtDoor tracking
                if ($tableArrivedAtDoor[$tableId] == 1) 
                    $cellVisibility = '';
                else 
                    $cellVisibility = 'hidden';
                echo "<td style='text-align: center' class='$cellVisibility'>";
                echo "<input id='arrivedAtDoor-$i' type='checkbox' class='inputCheckbox' name='arrivedAtDoor' value='1' onchange='participantUpdate(\"pizzaUpdate\",$i,$tableId,$userid[$i])'";
                if ($arrivedAtDoor[$i] == 1) echo "checked";
                echo ">";
                echo "</td>";

                // data
                if ($arrivedAtDoor[$i] == 1)
                    $lineData['arrivedAtDoor'] = 'Ja';
                else 
                    $lineData['arrivedAtDoor'] = 'Nej';

                // Price
                $price = floatval($price) + floatval($priceExtra);
                echo "<td>$price,-</td>";

                // data
                $lineData['Pris'] = floatval($price);

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

                array_push($dataForDownload, $lineData);
            }

        }
        $stmt->close();

        // Ending table
        echo "</thead></table></div></div>";

        // Download data as CSV
        echo "<div>";
        echo "<script>tableCSVContent = ".json_encode($dataForDownload).";</script>\n";
        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.2.0/papaparse.min.js" integrity="sha512-rKFvwjvE4liWPlFnvH4ZhRDfNZ9FOpdkD/BU5gAIA3VS3vOQrQ5BjKgbO3kxebKhHdHcNUHLqxQYSoxee9UwgA==" crossorigin="anonymous"></script>'."\n";
        echo "<button class='btn updateBtn' onclick='downloadData();'>Download data</button>";
        echo "</div>";

        // Participant edit
        if (isset($_GET['editUser']) and $_GET['editUser'] != "" AND $_GET['editUser'] != '0' AND in_array($_GET['editUser'],$userid)) {
            $userId = $_GET['editUser'];
            echo "<p style='height: 1rem;'></p>";
            echo "\n<script>var price = {};</script>";
            echo "<div>".$postError."</div>";
            echo "<form name='editForm-$i' id='editForm-$i' method='POST'><div id='userEdit' style='margin-top: 2rem;'>";
            echo "<input type='hidden' name='userId' value='$userId'>";
            echo "<h3>Opdater tilmelding - $userId</h3>";
            for ($i=0; $i < count($columnNameFront); $i++) {
                $html = "";
                // Setup for required label
                if ($required[$i] == 1) {$isRequired = "required"; $requiredStar = "<i style='color: red'>*</i>";} else {$isRequired = ""; $requiredStar = "";}
                if (in_array('unique',$specialName[$i])) $requiredStar .= " <i title='Dette input skal være unikt for hver tilmelding' style='cursor: help'>(unikt)</i>"; else $requiredStar .= "";
                // Setup for disabled
                if ($disabled[$i] == 1) $disabledClass = "hidden"; else $disabledClass = "";
                // Main writing of input
                $html .= "\n<div id='$columnId[$i]-div'>";
                $_POST[$columnNameBack[$i]] = $userData[$userId][$columnNameBack[$i]];
                if (!in_array($userData[$userId][$columnNameBack[$i]],$settingNameID))
                $_POST[$columnNameBack[$i]."Other"] = $userData[$userId][$columnNameBack[$i]];
                switch ($columnType[$i]) {
                    case "dropdown":
                        $html .= "\n<p class='$disabledClass'><label>$columnNameFront[$i]$requiredStar</label>";
                        // Getting settings category
                        $table_name = $wpdb->prefix . 'htx_settings_cat';
                        $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE tableId = ? AND  id = ? LIMIT 1");
                        $stmt->bind_param("ii", $tableId,  $settingCat[$i]);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if($result->num_rows === 0)  {echo "Noget gik galt...";} else {
                            while($row = $result->fetch_assoc()) {
                                $setting_cat_settingId = $row['id'];
                            }
                        }
                        $stmt->close();
    
                        // Getting dropdown content
                        $table_name = $wpdb->prefix . 'htx_settings';
                        $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE settingId = ? ORDER BY sorting");
                        $stmt->bind_param("i", $setting_cat_settingId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if($result->num_rows === 0)  {return $html .= "\nDer er på nuværende tidspunkt ingen mulige valg her<input type='hidden' name='name='$columnNameBack[$i]' value=''>";} else {
                            // Price function
                            if (count(array_intersect($specialName[$i],$possiblePriceFunctions)) > 0) $priceClass = 'priceFunction'; else $priceClass = '';
                            if (count(array_intersect($specialName[$i],$possiblePriceFunctions)) > 0) $priceFunction = "onchange='HTXJS_price_update()'"; else $priceFunction = '';
                            
                            // Writing first part of dropdown
                            $html .= "\n<select id='$columnId[$i]-input' name='$columnNameBack[$i]' oninput='HTX_frontend_js()' class='dropdown $disabledClass $priceClass' $priceFunction $isRequired>";
                            
                            // None input option
                            if (in_array('noneInput',$specialName[$i])) {
                                if($_POST[$columnNameBack[$i]] == 0) $postSelected = 'selected'; else $postSelected = '';
                                $html .= "\n<option value='0' $postSelected></option>";
                            }
    
                            // Writing dropdown options
                            while($row = $result->fetch_assoc()) {
                                // Getting data
                                $setting_settingName = $row['settingName'];
                                $setting_id = $row['id'];
    
                                // Set as selected from post
                                if($_POST[$columnNameBack[$i]] == $setting_id) $postSelected = 'selected'; else $postSelected = '';
    
                                // Write data
                                $html .= "\n<option value='$setting_id' $postSelected>".$setting_settingName."</option>";
    
                                if (count(array_intersect($specialName[$i],$possiblePriceFunctions)) > 0)
                                    $html .= "\n<script>price['$setting_id']='".$row['value']."';</script>";
                            }
    
                            // Finishing dropdown
                            $html .= "\n</select>";
    
                            // Other input option
                            if (in_array('otherInput',$specialName[$i])) {
                                if (isset($_POST[$columnNameBack[$i]."Other"]) and $_POST[$columnNameBack[$i]."Other"] != "")
                                    $otherInputValue = $_POST[$columnNameBack[$i]."Other"];
                                else 
                                    $otherInputValue = "";
                                $html .= "\n<small><i><label>Andet: </label>";
                                $html .= "\n<input name='$columnNameBack[$i]Other' type='text' placeholder='Andet' id='$columnId[$i]-input-other' style='max-width: 250px; margin-top: 10px' value='$otherInputValue'>";
                                $html .= "\n</i></small>";
                            }
                        }
                        $stmt->close();
                        $html .= "\n<small id='$columnId[$i]-text' class='form_warning_smalltext'></small>";
                        $html .= "\n</p>";
                    break;
                    case "user dropdown":
                        $html .= "\n<p class='$disabledClass'><label>$columnNameFront[$i]$requiredStar</label>";
                        // Getting settings category
                        $table_name = $wpdb->prefix . 'htx_settings_cat';
                        $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE tableId = ? AND  id = ? LIMIT 1");
                        $stmt->bind_param("ii", $tableId,  $settingCat[$i]);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if($result->num_rows === 0)  {echo "Noget gik galt...";} else {
                            while($row = $result->fetch_assoc()) {
                                $setting_cat_settingId = $row['id'];
                            }
                        }
                        $stmt->close();
    
                        // Getting dropdown content
                        $table_name = $wpdb->prefix . 'htx_settings';
                        $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE settingId = ? ORDER BY sorting AND settingName");
                        $stmt->bind_param("i", $setting_cat_settingId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if($result->num_rows === 0)  {return $html .= "\nDer er på nuværende tidspunkt ingen mulige valg her <input type='hidden' name='name='$columnNameBack[$i]' value=''>";} else {
                            
                            // Writing first part of dropdown
                            $html .= "\n<select id='$columnId[$i]-input' name='$columnNameBack[$i]' id='extraUserSettingDropdown-$i' oninput='HTX_frontend_js()' class='dropdown $disabledClass' $isRequired>";
    
                            // None input option
                            if (in_array('noneInput',$specialName[$i])) {
                                if($_POST[$columnNameBack[$i]] == 0) $postSelected = 'selected'; else $postSelected = '';
                                $html .= "\n<option value='0' $postSelected></option>";
                            }
    
                            // Writing dropdown options
                            while($row = $result->fetch_assoc()) {
                                // Getting data
                                $setting_settingName = $row['settingName'];
                                $setting_id = $row['id'];
    
                                // Set as selected from post
                                if($_POST[$columnNameBack[$i]] == $setting_id) $postSelected = 'selected'; else $postSelected = '';
    
                                // Write data
                                $html .= "\n<option value='$setting_id' $postSelected>".$setting_settingName."</option>";
                            }
    
                            // Finishing dropdown
                            $html .= "\n</select>";
    
                            // Possible to add a new input
                            $html .= "\n<small><i><label>Andet: </label>";
                            $html .= "\n<input name='$columnNameBack[$i]-extra' type='$format[$i]' id='extraUserSetting-$i' 
                            class='inputBox  $disabledClass' style='width: unset; margin-top: 5px;' value='".htmlspecialchars($_POST[$columnNameBack[$i].'-extra'])."'></i></small>";
                        }
                        $stmt->close();
                        $html .= "\n<small id='$columnId[$i]-text' class='form_warning_smalltext'></small>";
                        $html .= "\n</p>";
                    break;
                    case "radio":
                        $html .= "\n<p class='$disabledClass'><label id='$columnId[$i]-input'>$columnNameFront[$i]$requiredStar</label><br>";
                        // Getting settings category
                        $table_name = $wpdb->prefix . 'htx_settings_cat';
                        $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE tableId = ? AND  id = ? AND active = 1 LIMIT 1");
                        $stmt->bind_param("ii", $tableId,  $settingCat[$i]);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if($result->num_rows === 0)  {echo "Noget gik galt...";} else {
                            while($row2 = $result->fetch_assoc()) {
                                $setting_cat_settingId = $row2['id'];
                            }
                            // Disabled handling
                            if ($disabled == 1) $disabledClass = "disabled"; else $disabledClass = "";
    
                            // Getting radio content
                            $table_name3 = $wpdb->prefix . 'htx_settings';
                            $stmt3 = $link->prepare("SELECT * FROM `$table_name3` WHERE settingId = ? AND active = 1 ORDER by sorting ASC, value ASC");
                            $stmt3->bind_param("i", $setting_cat_settingId);
                            $stmt3->execute();
                            $result3 = $stmt3->get_result();
                            if($result3->num_rows === 0) $html .= "\nDer er på nuværende tidspunkt ingen mulige valg her<input type='hidden' name='name='$columnNameBack[$i]' value='' disabled>"; else {
                                // Price function
                                if (count(array_intersect($specialName[$i],$possiblePriceFunctions)) > 0) $priceClass = 'priceFunctionRadio'; else $priceClass = '';
                                if (count(array_intersect($specialName[$i],$possiblePriceFunctions)) > 0) $priceFunction = "onchange='HTXJS_price_update()'"; else $priceFunction = '';
    
                                // None input option
                                if (in_array('noneInput',$specialName[$i])) {
                                    if($_POST[$columnNameBack[$i]] == 0) $postSelected = 'checked="checked"'; else $postSelected = '';
                                    $html .= "\n<input type='radio' id='$columnNameBack[$i]-0' name='$columnNameBack[$i]' oninput='HTX_frontend_js()' value='0' class='inputBox $columnId[$i]-radio $disabledClass $priceClass' $priceFunction $postSelected>
                                    <label for='$columnNameBack[$i]-0'><i>Intet</i></label><br>";
    
                                    // Price for javascript
                                    if (count(array_intersect($specialName[$i],$possiblePriceFunctions)) > 0)
                                        $html .= "\n<script>price[0]='0';</script>";
                                }
                                while($row3 = $result3->fetch_assoc()) {
                                    // Getting data
                                    $setting_settingName = $row3['settingName'];
                                    $setting_id = $row3['id'];
    
                                    // Set as selected from post
                                    if($_POST[$columnNameBack[$i]] == $setting_id) $postSelected = 'checked="checked"'; else $postSelected = '';
    
                                    // Write data
                                    $html .= "\n<input type='radio' id='$columnNameBack[$i]-$setting_id' name='$columnNameBack[$i]' oninput='HTX_frontend_js()' value='$setting_id' class='inputBox $columnId[$i]-radio $disabledClass $priceClass' $priceFunction $postSelected>
                                    <label for='$columnNameBack[$i]-$setting_id'>$setting_settingName</label><br>";
    
                                    // Price for javascript
                                    if (count(array_intersect($specialName[$i],$possiblePriceFunctions)) > 0)
                                        $html .= "\n<script>price['$setting_id']='".$row3['value']."';</script>";
    
                                }
                                // Other input option
                                if (in_array('otherInput',$specialName[$i])) {
                                    $html .= "\n<small><i><label>Andet: </label>";
                                    $html .= "\n<input name='$columnNameBack[$i]Other' type='text' placeholder='Andet' id='$columnId[$i]-input-other' style='max-width: 250px; margin-top: 10px'>";
                                    $html .= "\n</i></small>";
                                }
                            }
                            $stmt3->close();
                        }
                        $stmt->close();
                        $html .= "\n<small id='$columnId[$i]-text' class='form_warning_smalltext'></small>";
                        $html .= "\n</p>";
                    break;
                    case "checkbox":
                        $_POST[$columnNameBack[$i]] = explode(",",$userData[$userId][$columnNameBack[$i]]);
                        $html .= "\n<p class='$disabledClass'><label id='$columnId[$i]-input'>$columnNameFront[$i]$requiredStar</label><br>";
                        // Getting settings category
                        $table_name = $wpdb->prefix . 'htx_settings_cat';
                        $stmt = $link->prepare("SELECT * FROM `$table_name` WHERE tableId = ? AND  id = ? AND active = 1 LIMIT 1");
                        $stmt->bind_param("ii", $tableId,  $settingCat[$i]);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if($result->num_rows === 0)  {echo "Noget gik galt...";} else {
                            while($row2 = $result->fetch_assoc()) {
                                $setting_cat_settingId = $row2['id'];
                            }
                            // Disabled handling
                            if ($disabled == 1) $disabledClass = "disabled"; else $disabledClass = "";
    
                            // Getting radio content
                            $table_name3 = $wpdb->prefix . 'htx_settings';
                            $stmt3 = $link->prepare("SELECT * FROM `$table_name3` WHERE settingId = ? AND active = 1 ORDER by sorting ASC, value ASC");
                            $stmt3->bind_param("i", $setting_cat_settingId);
                            $stmt3->execute();
                            $result3 = $stmt3->get_result();
                            if($result3->num_rows === 0) $html .= "\nDer er på nuværende tidspunkt ingen mulige valg her<input type='hidden' name='name='$columnNameBack[$i]' value='' disabled>"; else {
                                $html .= "\n<div class='formCreator_flexRow'>";
                                while($row3 = $result3->fetch_assoc()) {
                                    // Getting data
                                    $setting_settingName = $row3['settingName'];
                                    $setting_id = $row3['id'];
    
                                    // Set as selected from post
                                    if (isset($_POST[$columnNameBack[$i]])) {
                                        if(in_array($setting_id, $_POST[$columnNameBack[$i]])) $postSelected = 'checked="checked"'; else $postSelected = '';
                                    }
    
                                    // Price function
                                    if (count(array_intersect($specialName[$i],$possiblePriceFunctions)) > 0) $priceClass = 'priceFunctionCheckbox'; else $priceClass = '';
                                    if (count(array_intersect($specialName[$i],$possiblePriceFunctions)) > 0) $priceFunction = "onchange='HTXJS_price_update()'"; else $priceFunction = '';
    
                                    // Write data
                                    $html .= "\n<div class='checkboxDiv'><input type='checkbox' id='$columnNameBack[$i]-$setting_id' oninput='HTX_frontend_js()' class='$priceClass $columnId[$i]-checkbox' name='".$columnNameBack[$i]."[]' $priceFunction value='$setting_id' $postSelected>
                                    <label for='$columnNameBack[$i]-$setting_id'>$setting_settingName</label></div>";
    
                                    // Price for javascript
                                    if (count(array_intersect($specialName[$i],$possiblePriceFunctions)) > 0)
                                        $html .= "\n<script>price['$setting_id']='".$row3['value']."';</script>";
                                }
                                $html .= "\n</div>";
                            }
                            $stmt3->close();
                        }
                        $stmt->close();
                        $html .= "\n<small id='$columnId[$i]-text' class='form_warning_smalltext'></small>";
                        $html .= "\n</p>";
                    break;
                    case "text area":
                        $html .= "\n<h5 id='$columnId[$i]-input'>$columnNameFront[$i]</h5>";
                        $html .= "\n<p>$placeholderText[$i]</p>";
                    break;
                    case "spacing":
                        $html .= "\n<div style='width: 100%; height: ".$placeholderText[$i]."rem; margin: 0px; padding: 0px;'></div>";
                    break;
                    case "price":
                        if ($priceSet == false) {
                            if (!in_array($format[$i], $possiblePrice)) $format[$i] = "";
                            $html .= "\n<h5 id='$columnId[$i]-input'>$columnNameFront[$i]</h5>";
                            $html .= "\n<p>$placeholderText[$i] <span id='priceLine' onload=\"HTXJS_price_update()\">0</span> $format[$i]</p><script>setTimeout(() => {HTXJS_price_update()}, 500);</script>";
                            $priceSet = true;
                        }
                    break;
                    default:
                        if ($columnNameBack[$i] == 'email') $inputDisabled = 'disabled'; else $inputDisabled = '';
                        if ($format[$i] == 'textarea') $inputMethod = 'textarea'; else $inputMethod = 'input';
                        $html .= "\n<p class='$disabledClass'><label>$columnNameFront[$i]$requiredStar</label>";
                        $html .= "\n<$inputMethod $inputDisabled id='$columnId[$i]-input' name='$columnNameBack[$i]' type='$format[$i]' placeholder='$placeholderText[$i]' oninput='HTX_frontend_js();";
                        if ($format[$i] == 'range') $html .= "document.getElementById(\"$columnId[$i]-rangeValue\").innerHTML = document.getElementById(\"$columnId[$i]-input\").value;' min='$formatExtra[$i]' max='$specialNameExtra3[$i]' style='padding: 0px;' ";
                        else $html .= "'";
                        if ($format[$i] == 'tel') $html .= "pattern='$formatExtra[$i]' ";
                        $html .= "class='inputBox  $disabledClass' value='".$_POST[$columnNameBack[$i]]."' $isRequired>";
                        if ($format[$i] == 'textarea') $html .= "\n".$_POST[$columnNameBack[$i]]."\n</textarea>";
                        if ($format[$i] == 'tel') $html .= "\n<small>Format: $placeholderText[$i]</small>";
                        if ($format[$i] == 'range') $html .= "\n<small>værdi: <span id='$columnId[$i]-rangeValue'>$placeholderText[$i]</span></small>";
                        $html .= "\n<small id='$columnId[$i]-text' class='form_warning_smalltext'></small>";
                        $html .= "\n</p>";
                }
                $html .= "\n</div>";
                echo $html;
            }

            $html = "";
            // Writing script for showing elements based on other elements
            $html .= "\n<script>function HTX_frontend_js() {";
            // input field
            $inputtypeTextfield = array('inputbox', 'dropdown', 'user dropdown');
            for ($i=0; $i < count($columnId); $i++) {
                if (in_array('show', $specialName[$i])) {
                    if ($specialNameExtra[$i] != "") {
                        // Transfering special name extra 2
                        $html .= "\n var isValue = ".json_encode($specialNameExtra2[$i]).";";
                        if (in_array($columnTypeID[$specialNameExtra[$i]], $inputtypeTextfield)) {
                            // Use -input
                            if ($formatID[$specialNameExtra[$i]] == 'number' AND $columnTypeID[$specialNameExtra[$i]] == 'inputbox') {
                                if (preg_match('/[<>=!]{1}+[=]?+\d+/', htmlspecialchars_decode($specialNameExtra2[$i][0]), $output_array)) {
                                    $html .= "\n thatValue = document.getElementById('$specialNameExtra[$i]-input').value;";
                                    $html .= "\n if (thatValue $output_array[0]) 
                                        document.getElementById('$columnId[$i]-div').classList.remove('hidden'); 
                                        else document.getElementById('$columnId[$i]-div').classList.add('hidden');";
                                }
                            } else {
                                $html .= "\n thatValue = document.getElementById('$specialNameExtra[$i]-input').value;";
                                $html .= "\n if (isValue.includes(thatValue)) 
                                    document.getElementById('$columnId[$i]-div').classList.remove('hidden'); 
                                    else document.getElementById('$columnId[$i]-div').classList.add('hidden');";
                            }
                        } else if ($columnTypeID[$specialNameExtra[$i]] == 'radio') {
                            // Use -radio
                            $html .= "\n
                            $('.$specialNameExtra[$i]-radio').each(function() {
                                thatValue = $(this).val()
                                if($(this).is(':checked')) {
                                    if (isValue.includes(thatValue)) 
                                        document.getElementById('$columnId[$i]-div').classList.remove('hidden'); 
                                } else {
                                    if (isValue.includes(thatValue)) 
                                        document.getElementById('$columnId[$i]-div').classList.add('hidden');
                                }
                            });";
                        } else if ($columnTypeID[$specialNameExtra[$i]] == 'checkbox') {
                            // Use -checkbox
                            $html .= "\n
                            $('.$specialNameExtra[$i]-checkbox').each(function() {
                                thatValue = $(this).val()
                                if($(this).is(':checked')) {
                                    if (isValue.includes(thatValue)) 
                                        document.getElementById('$columnId[$i]-div').classList.remove('hidden'); 
                                } else {
                                    if (isValue.includes(thatValue)) 
                                        document.getElementById('$columnId[$i]-div').classList.add('hidden');
                                }
                            });";
                        } else {
                            // do nothing
                        }
                    }
                }
            }
            $html .= "\n};setTimeout(() => {HTX_frontend_js()}, 500);</script>";
            
            $html .= "\n<input name='tableId' value='$tableId' style='display: none'></p>";
    
            // Ending form with submit and reset buttons
            $table_name = $wpdb->prefix . 'htx_form_tables';
            $stmt = $link->prepare("SELECT * FROM $table_name WHERE id = ?");
            $stmt->bind_param("i", $tableId);
            $stmt->execute();
            $result = $stmt->get_result();
            if($result->num_rows === 0) exit('Something went wrong...');
            while($row = $result->fetch_assoc()) {
                $html .= "\n<p><button type='submit' name='submit' value='update' class='btn updateBtn'>";
                if ($row['registration'] == 1) {
                    $html .= "Tilmeld";
                } else {
                    $html .= "Indsend";
                }
                $html .= "</button> <button type='reset' name='reset' class='btn cancelBtn'>Nulstil</button></p></form>";
            }
            $stmt->close();

            echo $html;

            echo "</div>";
        }
    
    }

?>
