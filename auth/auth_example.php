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
    $user_id = cas_authenticate();
    if(isset($user_id) && $_SERVER["REQUEST_METHOD"] == "POST") { //If button was clicked...
        $temp_key = create_access_token($user_id, null, "TEMP"); //Create a temp_key
        $redirect = $_POST['redirect'];
        session_unset();
        session_destroy();
    }
?>

<html>
<head>
    <title>
        Login to ITaP Labs API
    </title>
    <style>
        .button-popup {
            width: 60%;
            height: 20%;
            position: absolute;
            top: 40%;
            left: 20%;
            background-color: #d19f2b;
            font-weight: bold;
            font-size: 3em;
            color: #e0e0e0;
            border: solid 3px #bf8b13;
            transition: border 0.5s, background-color 0.5s;
        }

        .button-popup:hover {
            background-color: #cead61;
            border: solid 3px #d19f2b;
        }

        div#disclaimer {
            width: 90%;
            position: absolute;
            bottom: 1em;
            left: 5%;
            text-align: center;
            color: #606060;
        }
    </style>
</head>
<body style='background-color: #aaaaaa'>
    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <input type="text" style="display: none;" name="redirect" value="<?php echo $_GET['redirect']; ?>">
        <input type="submit" class="button-popup" value="Click here to Login to ITaP Labs">
    </form>
    <div id="disclaimer">
        <p>
            By clicking this button, you agree to allow ITaP Labs to store cookies in your browser. The security of your information is very important to us and
            we have taken measures to make it more secure. Everything you do on the ITaP Labs tools is encrypted and secure. You are seeing this page, because it
            allows us to confirm your identity through CAS authentication.
        </p>
    </div>
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <script>
        var temp_key = "<?php echo $temp_key; ?>"; //This will be either an actual temp key (after button is pressed) or an empty string.
        var redirect = "<?php echo $redirect; ?>"; //Taken from the original request to this page
        if(temp_key != "") { //If there is actually a temp_key (button was pressed)
            window.location.replace(redirect + "?temp_key=" + temp_key); //Redirect back to application page with temp_key in query string.
        }
    </script>
</body>
</html>