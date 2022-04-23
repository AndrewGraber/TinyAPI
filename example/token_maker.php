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
    <script type="text/javascript" href="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <style>
        .circle-wrap .circle .mask,
        .circle-wrap .circle .fill {
            width: 100px;
            height: 100px;
            position: absolute;
            border-radius: 50%;
        }

        .mask .fill {
            clip: rect(0px, 50px, 100px, 0px);
            background-color: #2eaee6;
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
            border: 1px solid #9c9c9c;
        }

        .container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
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
        </div>
        <div class="container">
            <h1>Step 2: Token Exchange</h1>
            <p>
                Once the user has retrieved a temporary key, the app can make a request to the 'get_token' endpoint to exchange
                the temporary key for an Access Token, which will allow the app to start making requests to the API. This token
                lasts for 24 hours and can be easily stored in a Cookie. If the token is lost or expired, the user can start the
                process over to retrieve a new one.
            </p>

            <div>Temporary Key: <input disabled type="text" id="temp_key" value="<?php echo $has_temp_key ? $temp_key : "No temp key found. Complete step 1!"; ?>" /></div>
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
        </div>
        <div>

        </div>
    </div>
<script>
$(document).ready(function() {
    $("#token_exchange_button").click(async (e) => {
        var resp;
        try {
            resp = await axios.post('/auth/get_token.php', {
                scopes: ["available"],
                temp_key: temp_key_str,
                user_id: 
            });
        } catch (error) {
            console.error(error);
        }
    });
});
</script>
</body>
</html>