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
        <div>
            <form method="post" action="/auth/access_request.php?redirect=<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>">
                <input type="text" style="display: none;" name="service_name" value="ExampleApp">
                <input type="text" name="user_id">
                <input type="submit" class="button-popup" value="Authorize with TinyAPI">
            </form>
        </div>
        <div>
            <h1>Temporary Key</h1>
            <p>Value: <?php echo $has_temp_key ? $temp_key : "No temp key retrieved yet. Get one by submitting the form above!"; ?></p>
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
            <input <?php echo $user_id == "" ? "disabled" : ""; ?> type="text" id="user_id" value="<?php echo $user_id; ?>" />
            <div id="token_exchange_button">

            </div>
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