<?php
    // Scripts with all sorts of code, that is written in either JS og CSS

    // Loading parameters - backend
    function HTX_load_standard_backend() {
        // Style

        // Ajax and icons
        echo '<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>';
        echo '<link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">';

        // Alert window
        HTX_information_alert_backend();
    }

    // Information alert code - Backend
    function HTX_information_alert_backend() {
        // HTML for information alert
        echo '<div id="informationwindow"></div>';
        // JS for information alert
        echo "<script>
        /*informationwindow inserter - by Mikkel Albrechtsen */
        informationwindowInsertIDDONOTCHANGE = 0;
        function informationwindowInsert(cat,text,speciel) {
            informationwindowInsertIDDONOTCHANGE +=1;
            id = informationwindowInsertIDDONOTCHANGE;
            if (cat == 1) {
                cat = \"succes\"
                cattext = \"Success\";
            } else if (cat == 2) {
                cat = \"warning\";
                cattext = \"Advarsel\";
            } else if (cat == 3) {
                cat = \"error\"
                cattext = \"Error!\";
            } else {
                return 0;
            }
            if (speciel != \"\") {
                element = \"<div id='IW\"+id+\"' class='succesWindows Windows \"+cat+\"' onclick='(\"+speciel+\");informationwindowremove(\"+id+\")'>\";
            } else {
                element = \"<div id='IW\"+id+\"' class='succesWindows Windows \"+cat+\"' onclick='(informationwindowremove(\"+id+\"))'>\";
            }
            element += \"<p id='IWTH\"+id+\"' class='infoText infoTextHeader'>\"+cattext+\"</p>\";
            element += \"<p id='IWT\"+id+\"' class='infoText'>\"+text+\"</p>\";
            element += \"<div id='IWS\"+id+\"' class='statusBar'>\";
            element += \"</div></div>\";
            $( \"#informationwindow\" ).append( element );
            setTimeout(function(){ document.getElementById(\"IWS\"+id).classList.add('statusClosing');  removeDocInfo(id)}, 10);
            function removeDocInfo(id) {setTimeout(function(){ document.getElementById(('IW'+id)).remove(); }, 5000);}
            
        }
        function informationwindowremove(id) {
            document.getElementById(\"IW\"+id).remove();
        }
        </script>";
        // CSS for information alert
        echo "<style>
        /*overlay windows notification*/
        :root {
            --submit-color-primary: #70ff41;
            --submit-color-secondary: #baffa3;
            --delete-color-primary: #ff0000;
            --delete-color-secondary: #ff4e4e;
            --cancel-color-primary: #ffa500;
            --cancel-color-secondary: #ffc14e;
            --transition-speed: 600ms;
            --transition-speed-box: 600ms;
            --transition-speed-table: calc(var(--transition-speed)/2);
        }
        
        #informationwindow {
            position:absolute;
            position: fixed;
            bottom:0;
            right:0;
            z-index: 75;
            display: flex;
            justify-content: flex-end;
            align-self: flex-start;
            flex-direction: column;
            padding-right: 1rem;
            color: black;
        }
        #informationwindow .Windows {
            transition: background-color var(--transition-speed-table) ease;
            transition: filter var(--transition-speed-table) ease;
            width: 15rem;
            opacity: 90%;
            border-radius: 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-evenly;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        #informationwindow .Windows:last-child {
            justify-content: flex-end;
        }
        #informationwindow .Windows:hover {
            filter: saturate(1.5);
        }
        #informationwindow .Windows .statusBar {
            transition: background-color var(--transition-speed-table) ease;
            transition: width 5s ease;
            height: 0.5rem;
            width: 100%;
            margin-top:1rem;
        }
        
        #informationwindow .Windows .statusClosing {
            width: 0%;
        }
        #informationwindow .infoText {
            opacity: 1;  /* Opacity for Modern Browsers */
            filter: alpha(opacity=100);  /* Opacity for IE8 and lower */
            zoom: 1;  /* Fix for IE7 */
            text-align: center;
            padding: 1rem;
            padding-top: 0rem;
            padding-bottom: 0rem;
            margin-bottom: 0rem;
            margin-top: 0rem;
        }
        #informationwindow .infoTextHeader {
            font-weight: bold;
            opacity: 1;  /* Opacity for Modern Browsers */
            filter: alpha(opacity=1010);  /* Opacity for IE8 and lower */
            zoom: 1;  /* Fix for IE7 */
            text-align: center;
            padding: 1rem;
            padding-bottom: 0.5rem;
            margin-bottom: 0rem;
        }
        .succes {
            background-color: var(--submit-color-secondary);
        }
        .succes .statusBar {
            background-color: var(--submit-color-primary);
        }
        .warning {
            background-color: var(--cancel-color-secondary);
        }
        .warning .statusBar {
            background-color: var(--cancel-color-primary);
        }
        .error {
            background-color: var(--delete-color-secondary);
            color: white;
        }
        .error .statusBar {
            background-color: var(--delete-color-primary);
        }
        </style>";
    }


        