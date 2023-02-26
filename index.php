<?php
    header('Content-Type: text/html; charset=utf-8');
    
    $passphrase = "[passphrase to encrypt GET variable with settings]";
    $cipher_algo = "AES-128-CTR";
    $settings = "";

    $langs = array(
        'BG' => 'BG 🇧🇬 - Bulgarian',
        'CS' => 'CS 🇨🇿 - Czech',
        'DA' => 'DA 🇩🇰 - Danish',
        'DE' => 'DE 🇩🇪 - German',
        'EL' => 'EL 🇬🇷 - Greek',
        'EN' => 'EN 🇬🇧 - English',
        'ES' => 'ES 🇪🇸 - Spanish',
        'ET' => 'ET 🇪🇪 - Estonian',
        'FI' => 'FI 🇫🇮 - Finnish',
        'FR' => 'FR 🇫🇷 - French',
        'HU' => 'HU 🇭🇺 - Hungarian',
        'ID' => 'ID 🇮🇩 - Indonesian',
        'IT' => 'IT 🇮🇹 - Italian',
        'JA' => 'JA 🇯🇵 - Japanese',
        'KO' => 'KO 🇰🇷 - Korean',
        'LT' => 'LT 🇱🇹 - Lithuanian',
        'LV' => 'LV 🇱🇻 - Latvian',
        'NB' => 'NB 🇳🇴 - Norwegian',
        'NL' => 'NL 🇳🇱 - Dutch',
        'PL' => 'PL 🇵🇱 - Polish',
        'PT' => 'PT 🇵🇹 - Portuguese',
        'RO' => 'RO 🇷🇴 - Romanian',
        'RU' => 'RU 🇷🇺 - Russian',
        'SK' => 'SK 🇸🇰 - Slovak',
        'SL' => 'SL 🇸🇮 - Slovenian',
        'SV' => 'SV 🇸🇪 - Swedish',
        'TR' => 'TR 🇹🇷 - Turkish',
        'UK' => 'UK 🇺🇦 - Ukrainian',
        'ZH' => 'ZH 🇨🇳 - Chinese'
    );

    if(!empty($_GET['set']))
    {
        $set = addslashes(strip_tags($_GET['set']));
        $decrypted_set = openssl_decrypt($set, $cipher_algo, $passphrase);
        $explode = explode(";", $decrypted_set);
        $token = $explode[0];
        $lang1 = $explode[1];
        $lang2 = $explode[2];
        $instance = $explode[3];
    }
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="Author" content="Tomasz Dunia">
    <meta name="Description" content="Tool that helps you post in two languages on Mastodon." />
    <meta name="Keywords" content="mastodon, fediverse, bilingual, post, toot, tool, deepl, translator" />
    <meta name="viewport" content="width=device-width, initial-scale=0.8, maximum-scale=0.8">
    <title>Bilingual Posting Tool for Mastodon</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon"/>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon"/>
    
    <link rel="stylesheet" href="css/main.css">
</head>

<body>
    <div class="container">
        <h1 style="text-align: center; margin-bottom: 50px" onClick="location.href='/?set=<?php echo $set; ?>';">Bilingual Posting Tool<br>for Mastodon</h1>

<?php
    if(empty($_POST))
    {
?>

        <form action="/?set=<?php echo $set; ?>" method="post">
            <div class="header">Choose languages:</div>
            <div class="label">
            <?php
                echo "<select class=\"select1\" name=\"lang1\" required>";
                echo "<option value=\"\">-FROM-</option>";
                foreach($langs as $key => $value)
                {
                    if($lang1 == $key)
                    {
                        echo "<option value=\"".$key."\" selected=\"selected\">".$value."</option>";
                    }
                    else
                    {
                        echo "<option value=\"".$key."\">".$value."</option>";
                    }
                }
                echo "</select>";
            ?>
                =>
            <?php
                echo "<select class=\"select1\" name=\"lang2\" required>";
                echo "<option value=\"\">-TO-</option>";
                foreach($langs as $key => $value)
                {
                    if($lang2 == $key)
                    {
                        echo "<option value=\"".$key."\" selected=\"selected\">".$value."</option>";
                    }
                    else
                    {
                        echo "<option value=\"".$key."\">".$value."</option>";
                    }
                }
                echo "</select>";
            ?>
            </div>
            <div class="header">Message:</div>
            <textarea class="textarea1" name="message" placeholder="Write here in your native language..." autocomplete="off" oninput='this.style.height = "";this.style.height = this.scrollHeight + "px"' required><?php echo $message; ?></textarea>

            <div class="header">Settings:</div><br>

            <div class="label">Your DeepL API key (<a href="https://www.deepl.com/en/pro-checkout/account?productId=1200&yearly=false&trial=false" target="_blank">you can get it for free</a>):</div>
            <input type="text" class="input1" name="token" placeholder="API Token..." value="<?php echo $token; ?>" autocomplete="on" required>

            <div class="label">Your Mastodon instance URL:</div>
            <input type="text" class="input1" name="instance" placeholder="Example: mstdn.social" value="<?php echo $instance; ?>" autocomplete="on"><br>

            <button type="submit" class="button button_submit" name="PrepareToot" value="PrepareToot">Prepare toot!</button>
        </form>

<?php
    }
    else
    {
        if($_POST['PrepareToot'])
        {
            $message = addslashes(strip_tags($_POST['message']));
            $token = addslashes(strip_tags($_POST['token']));
            $lang1 = addslashes(strip_tags($_POST['lang1']));
            $lang2 = addslashes(strip_tags($_POST['lang2']));
            $instance = addslashes(strip_tags($_POST['instance']));
            if(empty($instance))
            {
                $disabled_tooting = true;
            }
            $parsed_url = parse_url($instance);
            if(!empty($parsed_url['host']))
            {
                $instance = $parsed_url['host'];
            }
            else
            {
                $instance = $parsed_url['path'];
                $instance = str_replace("https://", "", $instance);
                $instance = str_replace("http://", "", $instance);
                $instance = str_replace("www.", "", $instance);
                $instance = str_replace("/", "", $instance);
            }

            $headers = [
                "Authorization: DeepL-Auth-Key ".$token
            ];

            $data = array(
                "text" => $message,
                "target_lang" => $lang2,
                "source_lang" => $lang1
            );

            $translate = curl_init();
            curl_setopt($translate, CURLOPT_URL, "https://api-free.deepl.com/v2/translate");
            curl_setopt($translate, CURLOPT_POST, 1);
            curl_setopt($translate, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($translate, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($translate, CURLOPT_POSTFIELDS, $data);
                                
            $return = json_decode(curl_exec($translate), true); // true means convert object into array
            curl_close ($translate);

            $translated_message = $return['translations'][0]['text'];

            $flags = array(
                'BG' => '🇧🇬',
                'CS' => '🇨🇿',
                'DA' => '🇩🇰',
                'DE' => '🇩🇪',
                'EL' => '🇬🇷',
                'EN' => '🇬🇧',
                'ES' => '🇪🇸',
                'ET' => '🇪🇪',
                'FI' => '🇫🇮',
                'FR' => '🇫🇷',
                'HU' => '🇭🇺',
                'ID' => '🇮🇩',
                'IT' => '🇮🇹',
                'JA' => '🇯🇵',
                'KO' => '🇰🇷',
                'LT' => '🇱🇹',
                'LV' => '🇱🇻',
                'NB' => '🇳🇴',
                'NL' => '🇳🇱',
                'PL' => '🇵🇱',
                'PT' => '🇵🇹',
                'RO' => '🇷🇴',
                'RU' => '🇷🇺',
                'SK' => '🇸🇰',
                'SL' => '🇸🇮',
                'SV' => '🇸🇪',
                'TR' => '🇹🇷',
                'UK' => '🇺🇦',
                'ZH' => '🇨🇳'
            );

            $toot = $flags[$lang2]." ".$translated_message."\n\r".$flags[$lang1]." ".$message;

            $settings = $token.";".$lang1.";".$lang2.";".$instance;
            $set = openssl_encrypt($settings, $cipher_algo, $passphrase);
?>

        <form action="/?set=<?php echo $set; ?>" method="post">
            <textarea class="textarea1" id="textarea" name="toot" autocomplete="off" oninput='this.style.height = "";this.style.height = this.scrollHeight + "px"'><?php echo $toot; ?></textarea>

            <button type="submit" class="button button_submit <?php if($disabled_tooting === true) { echo "disabled"; } ?>" name="PostToot" value="PostToot" <?php if($disabled_tooting === true) { echo "disabled"; } ?>>Post toot!</button>
        </form>
            <div class="label">OR</div>
            <button type="button" class="button button_submit" id="CopyButton" name="CopyButton" onclick="copy()">Copy</button>
            <br><br>
            <div class="label">Next time, use the link given below so you don't have to enter DeepL API key and URL of your Mastodon instance again.</div>
            <input type="text" class="input1" value="https://bilangpost.tomaszdunia.pl/?set=<?php echo $set; ?>" disabled>


        <script>
            function copy() {
                let textarea = document.getElementById("textarea");
                textarea.select();
                document.execCommand("copy");
                var btn = document.getElementById("CopyButton");
                btn.innerHTML = "Copied!";
            }
        </script>

<?php
        }
        elseif($_POST['PostToot'] == "PostToot")
        {
            $toot = strip_tags($_POST['toot']);
            //$toot = str_replace("\n\r", "%0A", $toot);
            $toot = rawurlencode($toot);
            $post = "https://".$instance."/share?text=".$toot;
            header("Location: ".$post);
            echo $post;
        }
    }
?>

        <?php include("footer.html"); ?>
    </div>
</body>

</html>
