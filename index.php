<?php
$languages = glob('languages/*.po');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Scratch translator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style type="text/css">
        .form {
            width: 100%;
            padding: 19px 29px 29px;
        }

        textarea {
            box-sizing: border-box;
            width: 96%;
            height: 300px;
        }
    </style>
    <link href="bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
</head>
<body>
<form class="form" method="post">
    <h2 class="form-heading">Scratch translator</h2>

    <p>
        <label for="original">Source</label>
        <textarea id="original" name="original"><?php if (isset($_POST['original'])) echo htmlentities($_POST['original']) ?></textarea><br/>

        <label for="language">Target language</label>
        <select id="language" name="language">
            <?php
            foreach ($languages as $language) {
                echo "<option>$language</option>";
            }
            ?>
        </select><br/>
        <button class="btn btn-large btn-primary" type="submit">Translate</button>
    </p>

    <?php
    if (isset($_POST['original']) && isset($_POST['language']) && in_array($_POST['language'], $languages)) {
        $translated_text = $original_text = $_POST['original'];

        require('poparser/poparser.php');
        $poparser = new I18n_Pofile();

        $entries = $poparser->read($_POST['language']);
        $entries_additional = $poparser->read($_POST['language'] . '.additional');
        $entries = array_merge($entries, $entries_additional);

        // Iterate over the entries in the po file
        foreach ($entries as $entry) {
            // Ignore entries that have a missing or empty msgid
            if (!isset($entry['msgid']) || !$entry['msgid']) {
                continue;
            }

            // Construct the regex for the source text
            $source = $entry['msgid'];
            $source = preg_replace('/%[a-zA-Z]/', '([a-zA-Z0-9_.\-!\?]+(?:\s[a-zA-Z0-9_.\-!\?]+)?)', $source);
            $source = '(^[\s\t]*|\()' . $source . '([\s\t]*$|\))';

            // Construct the replacement pattern
            $target = implode('', $entry['msgstr']); // the msgstr can be made of several lines which we need to join
            $i = 1;
            while (preg_match('|%[a-zA-Z]|', $target)) {
                $target = preg_replace('|%[a-zA-Z]|', '\\\\' . ++$i, $target, 1);
            }
            $target = '\\1' . $target . '\\' . ++$i;

            $delimiter_start = '{';
            $delimiter_end = '}';

            // Function to handle the translations
            $handle_translations = function ($matches) use ($entries, $delimiter_start, $delimiter_end, $source, $target) {
                $translated_text = preg_replace($delimiter_start . $source . $delimiter_end . 'Umui', $target, $matches[0]);

                // Handle "inner" translations
                foreach ($entries as $entry) {
                    if (!isset($entry['msgid']) || !$entry['msgid']) {
                        continue;
                    }

                    // Replace the '%s', '%n', etc placeholders from the po file with a generic regex
                    $source = preg_replace('/%[a-zA-Z]/', '([a-zA-Z0-9_.\-!\?]+)', $entry['msgid']);
                    $target = implode('', $entry['msgstr']);
                    $i = 1;
                    while (preg_match('|%[a-zA-Z]|', $target)) {
                        $target = preg_replace('|%[a-zA-Z]|', '\\\\' . ++$i, $target, 1);
                    }

                    $translated_text = preg_replace($delimiter_start . '(\W)' . $source . '(\W)' . $delimiter_end . 'Umui', '\\1' . $target . '\\' . ++$i. '\\' . ++$i, $translated_text);
                }
                return $translated_text;
            };

            // Check if the current entry from the po file can be used
            $translated_text = preg_replace_callback($delimiter_start . $source . $delimiter_end . 'Umui', $handle_translations, $translated_text);
        }

        // Output the result
        echo '<p><label for="translation">Translation:</label><textarea id="translation">' . htmlentities($translated_text) . '</textarea></p>';

        // Output a diff (helpful for a quick check)
        require __DIR__ . '/php-diff/lib/Diff.php';
        require __DIR__ . '/php-diff/lib/Diff/Renderer/Html/SideBySide.php';
        $options = array(
            'ignoreWhitespace' => true,
        );
        $diff = new Diff(explode("\n", $original_text), explode("\n", $translated_text), $options);
        $renderer = new Diff_Renderer_Html_SideBySide;
        echo $diff->Render($renderer);
    }
    ?>
</form>
</body>
</html>
