<?php

$has_temp_key = false;
$temp_key = $user_id = "";

if(isset($_GET['temp_key'])) {
    $has_temp_key = true;
    $temp_key = $_GET['temp_key'];
    $user_id = $_GET['user_id'];
}

?>

<html>
<head>
    <title>Example Token Maker</title>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        .circle-wrap .circle .mask,
        .circle-wrap .circle .fill {
            width: 100px;
            height: 100px;
            position: absolute;
            border-radius: 50%;
        }

        .mask .fill {
            clip: rect(0px, 50px, 100px, 0px);
            background-color: #5e89ff;
        }

        .circle-wrap .circle .mask {
            clip: rect(0px, 100px, 100px, 50px);
        }

        .mask.full,
        .circle .fill {
            animation: fill linear 60s;
            transform: rotate(-180deg);
        }

        @keyframes fill {
            0% {
                transform: rotate(180deg);
            }
            100% {
                transform: rotate(0deg);
            }
        }

        .circle-wrap {
            width: 100px;
            height: 100px;
            background: #ebebeb;
            border-radius: 50%;
            border: 1px solid rgba(232, 232, 232, 0.2);
        }

        .container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .outer {
            width: 94vw;
            height: 94vh;
            margin-left: 3vw;
            margin-top: 3vh;
        }

        #token_exchange_button {
            padding: 1em;
            border: 4px solid #5e89ff;
            background-color: #324987;
            color: #dedede;
            border-radius: 2em;
            transition: color 0.2s ease, background-color 0.2s ease, border 0.2s ease;
        }

        #token_exchange_button:hover {
            border: 4px solid #324987;
            background-color: #5e89ff;
            color: #324987;
            cursor: pointer;
            margin-bottom: 1em;
        }

        .bottom-line {
            width: 80%;
            border-bottom: 1px solid 
        }
    </style>
</head>
<body>
    <div class="container outer">
        <div class="container">
            <h1>Step 1: Authorization</h1>
            <p>
                In this first step, the app redirects the user to the 'access_request' page. The app which sent the user
                will provide their user_id in this request (likely pulled from a session variable, rather than allowing
                the user to enter it). Once the user authorizes the request, the access_request page will return a temporary
                key to the url provided by the original app.
            </p>
            <form method="post" action="/auth/access_request.php?redirect=<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>">
                <input type="text" style="display: none;" name="service_name" value="ExampleApp">
                <input type="text" name="user_id">
                <input type="submit" class="button-popup" value="Authorize with TinyAPI">
            </form>
            <div class="bottom-line"></div>
        </div>
        <div class="container">
            <h1>Step 2: Token Exchange</h1>
            <p>
                Once the user has retrieved a temporary key, the app can make a request to the 'get_token' endpoint to exchange
                the temporary key for an Access Token, which will allow the app to start making requests to the API. This temporary
                token is only valid for 60 seconds (shown by the timer), so be sure to exchange it quickly!
            </p>

            <div>Temporary Key: <input disabled type="text" id="temp_key" value="<?php echo $has_temp_key ? $temp_key : "No temp key found"; ?>" /></div>
            <div>user_id on Temp Key: <input disabled type="text" id="user_id" value="<?php echo $user_id; ?>" /></div>
            <div class="circle-wrap">
                <div class="circle">
                    <div class="mask half">
                        <div class="fill"></div>
                    </div>
                    <div class="mask full">
                        <div class="fill"></div>
                    </div>
                </div>
            </div>
            <div id="token_exchange_button">Exchange key for Token</div>
            <div class="bottom-line"></div>
        </div>
        <div>
            <h1>Finish: Access Token</h1>
            <p>
                Now the temporary key has been exchanged for a valid Access Token. This token lasts for 24 hours and can be
                easily stored in a Cookie. If the token is lost or expired, the user can start the process over to retrieve
                a new one.
            </p>
            <input disabled type="text" id="access_token" value="No token found" />
        </div>
    </div>
<script>
$(document).ready(function() {
    var user_id = $("#user_id").val();
    var temp_key = $("#temp_key").val();
    $("#token_exchange_button").click(async (e) => {
        var resp;
        try {
            resp = await axios.post('/auth/get_token.php', {
                scopes: ["available"],
                temp_key: temp_key_str,
                user_id: user_id
            });
        } catch (error) {
            console.error(error);
        }

        if(typeof resp !== 'undefined') {
            console.log(resp);
        }
    });
});
</script>
</body>
</html>