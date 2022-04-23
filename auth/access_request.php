<?php
//*
//Error Reporting Remove comments when Debugging
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE); // */

/**
 * This API endpoint is the first step in the authentication flow of the API.
 * 
 * This page  will enforce CAS authentication. If a current CAS session is already in place, it will grab 
 * information from that session. If not, it will redirect to the Purdue CAS login page (requires BoilerKey)
 * and route back to this page after the user has logged in. Then, the user will need to click the button on
 * this page to authorize us to use cookies to store the access token.
 * 
 * Once the user has pressed the button, this page will redirect to the url given in the 'redirect' query
 * parameter with the temp_key it generated as a query parameter. For example, if your website is https://example.com,
 * you'd redirect to "[this url]/cas_auth.php?redirect=https://example.com/page/to/redirect/to" and when the user
 * clicks the login button, they will be redirected to "https://example.com/page/to/redirect/to?temp_key=TEMP-xxxx"
 * 
 * The generated temp_key will expire 60 seconds after it is created, so it should be immediately exchanged for
 * an access token via the {@see \api\auth\get_token get_token} endpoint.
 * 
 * @package api\auth
 * @license All Rights Reserved
 * @copyright Andrew Graber
 * @author Andrew Graber <graber15@purdue.edu>
 */
    require_once("auth_functions.php");
    session_start();

    $temp_key = $redirect = "";

    $service_name = "NULL";
    if(isset($_POST['service_name'])) {
        $service_name = $_POST['service_name'];
    } else if(isset($_GET['redirect'])) {
        $host = parse_url($_GET['redirect'], $PHP_URL_HOST);
        str_replace("www.", "", $host);
        $service_name = $host;
    }

    if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id']) && $_POST['user_id'] != "" && isset($_POST['redirect'])) { //If button was clicked...
        $temp_key = create_access_token($_POST['user_id'], null, "TEMP"); //Create a temp_key
        $redirect = $_POST['redirect'];
        session_unset();
        session_destroy();
    }
?>

<html>
<head>
    <title>
        TinyAPI Authorization
    </title>
    <style>
        .button-popup {
            flex: 100%;
            height: 100%;
            width: 100%;
            background-color: #d19f2b;
            font-weight: bold;
            font-size: 2em;
            color: #e0e0e0;
            border: solid 3px #bf8b13;
            transition: border 0.5s, background-color 0.5s;
        }

        .button-popup:hover {
            background-color: #cead61;
            border: solid 3px #d19f2b;
        }

        .container {
            position: absolute;
            width: 90vw;
            height: 90vh;
            top: 5vh;
            left: 5vh;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .form {
            flex: 0.5;
            display: flex;
            width: 100%;
            margin-bottom: 5vh;
        }

        div#info {
            flex: 0.25;
            text-align: center;
            color: #606060;
        }

        .blank {
            flex: 0.25;
        }
    </style>
</head>
<body style='background-color: #aaaaaa'>
    <div class="container">
        <div class="blank"></div>
        <form method="post" class="form" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <input type="text" style="display: none;" name="redirect" value="<?php echo $_GET['redirect']; ?>">
            <input type="text" style="display: none;" name="user_id" value="<?php echo $_POST['user_id']; ?>">
            <input type="submit" class="button-popup" value="Login to TinyAPI with <?php echo $service_name; ?>">
        </form>
        <div id="info">
            <p>
                Clicking the button above will use the user_id provided in the POST data to generate a temporary token
                and redirect you to the url provided in the query string value 'redirect', which is set by whatever site
                sent you to this one. The temporary token is valid for up to 60 seconds and will be returned in the query
                string as 'temp_key'. Once the redirect is complete, the site that made the request for the temporary key
                can then make a request to get_token.php to trade the temporary key for an access token that lasts 24 hours.
            </p>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <script>
        var temp_key = "<?php echo $temp_key; ?>"; //This will be either an actual temp key (after button is pressed) or an empty string.
        var redirect = "<?php echo $redirect; ?>"; //Taken from the original request to this page
        if(temp_key != "") { //If there is actually a temp_key (button was pressed)
            window.location.replace(redirect + "?temp_key=" + temp_key + "&user_id=<?php echo $_POST['user_id']; ?>"); //Redirect back to application page with temp_key in query string.
        }
    </script>
</body>
</html>