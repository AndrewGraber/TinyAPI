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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/js-cookie/3.0.1/js.cookie.min.js" integrity="sha512-wT7uPE7tOP6w4o28u1DN775jYjHQApdBnib5Pho4RB0Pgd9y7eSkAV1BTqQydupYDB9GBhTcQQzyNMPMV3cAew==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            background-color: #474747;
            color: #dedede;
        }

        h1, p {
            margin: 0.5em 0;
            text-align: center;
        }

        .space-around {
            margin: 0.25em 0;
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
            background: #474747;
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
            margin-bottom: 1em;
        }

        #token_exchange_button:hover {
            border: 4px solid #324987;
            background-color: #5e89ff;
            color: #324987;
            cursor: pointer;
        }

        .bottom-line {
            width: 90%;
            border-bottom: 1px solid #dedede;
        }

        #access_token {
            width: 50em;
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

            <div class="space-around">Temporary Key: <input disabled type="text" id="temp_key" value="<?php echo $has_temp_key ? $temp_key : "No temp key found"; ?>" /></div>
            <div class="space-around">user_id on Temp Key: <input disabled type="text" id="user_id" value="<?php echo $user_id; ?>" /></div>
            <div class="circle-wrap space-around">
                <div class="circle">
                    <div class="mask half">
                        <div class="fill"></div>
                    </div>
                    <div class="mask full">
                        <div class="fill"></div>
                    </div>
                </div>
            </div>
            <div class="space-around" id="token_exchange_button">Exchange key for Token</div>
            <div class="bottom-line"></div>
        </div>
        <div class="container">
            <h1>Finish: Access Token</h1>
            <p>
                Now the temporary key has been exchanged for a valid Access Token. This token lasts for 24 hours and can be
                easily stored in a Cookie. If the token is lost or expired, the user can start the process over to retrieve
                a new one.
            </p>
            <div class="space-around">Access Token: <input disabled type="text" id="access_token" value="No token found" /></div>
        </div>
    </div>
<script>
$(document).ready(function() {
    var cookie = Cookies.get('access_token');
    if(typeof cookie !== 'undefined') {
        $("#access_token").val(cookie);
    }

    var user_id = $("#user_id").val();
    var temp_key = $("#temp_key").val();
    $("#token_exchange_button").click(async (e) => {
        var resp;
        try {
            resp = await axios.post('/auth/get_token.php', {
                scopes: ["available"],
                temp_key: temp_key,
                user_id: user_id
            });
        } catch (error) {
            console.error(error);
        }

        if(typeof resp !== 'undefined') {
            console.log(resp);
            $("#access_token").val(resp.data.token);
            Cookies.remove('access_token', {path: ''});
            Cookies.set('access_token', resp.data.token, {expires: 1, path: ''});
        }
    });
});
</script>
</body>
</html>