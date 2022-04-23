<?php

$has_temp_key = false;
$temp_key = "";

if(isset($_POST['temp_key'])) {
    $has_temp_key = true;
    $temp_key = $_POST['temp_key'];
}

?>

<html>
<head>
    <title>Example Token Maker</title>
</head>
<body>
    <form method="post" action="/auth/access_request.php?redirect=<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>">
        <input type="text" style="display: none;" name="service_name" value="ExampleApp">
        <input type="text" name="user_id">
        <input type="submit" class="button-popup" value="Authorize with TinyAPI">
    </form>

    <br>

    <div>
        <h1>Temporary Key</h1>
        <p>Value: <?php echo $has_temp_key ? $temp_key : "No temp key retrieved yet. Get one by submitting the form above!"; ?></p>
    </div>
</body>
</html>