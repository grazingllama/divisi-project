<?php
// Enable full error reporting (for debugging; adjust for production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'upload.log');
error_log("===== Upload Process Started =====\n");

// Use config.php (which should define the Database class and settings)
require 'config.php';

// Ensure $pdo is set correctly.
$database = new Database();
$pdo = $database->getConnection();
if (!$pdo) {
    error_log("Database connection error.\n");
    die("Database connection error.");
}

// Fetch existing artists, composers, instrument groups, and instruments.
$artists = $pdo->query("SELECT id, name FROM artists")->fetchAll(PDO::FETCH_ASSOC);
$composers = $pdo->query("SELECT id, name FROM composers")->fetchAll(PDO::FETCH_ASSOC);
$instrument_groups = $pdo->query("SELECT id, name FROM instrument_groups")->fetchAll(PDO::FETCH_ASSOC);
$instruments = $pdo->query("SELECT id, name, instrument_group_id FROM instruments")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("POST data: " . print_r($_POST, true) . "\n");
    error_log("POST request received.\n");

    // Define the temporary upload directory.
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        if (mkdir($target_dir, 0777, true)) {
            error_log("Created directory: $target_dir\n");
        } else {
            error_log("Failed to create directory: $target_dir\n");
        }
    } else {
        error_log("Directory $target_dir already exists.\n");
    }

    // --- Process New Artists ---
    if (!empty($_POST['new_artists']) && is_array($_POST['new_artists'])) {
        error_log("Processing new artists...\n");
        foreach ($_POST['new_artists'] as $index => $artistName) {
            if (trim($artistName) != "") {
                $artistInfo = isset($_POST['artist_infos'][$index]) ? $_POST['artist_infos'][$index] : null;
                $stmt = $pdo->prepare("INSERT INTO artists (name, info) VALUES (?, ?)");
                if ($stmt->execute([$artistName, $artistInfo])) {
                    $newArtistId = $pdo->lastInsertId();
                    error_log("Inserted new artist: '$artistName' (ID: $newArtistId)\n");
                    $_POST['artists'][] = $newArtistId;
                } else {
                    error_log("Failed to insert new artist: '$artistName'\n");
                }
            }
        }
    }

    // --- Process New Composer ---
    if (!empty($_POST['new_composer'])) {
        error_log("Processing new composer: " . $_POST['new_composer'] . "\n");
        $stmt = $pdo->prepare("INSERT INTO composers (name, info) VALUES (?, ?)");
        if ($stmt->execute([$_POST['new_composer'], $_POST['composer_info'] ?? null])) {
            $newComposerId = $pdo->lastInsertId();
            error_log("Inserted new composer (ID: $newComposerId)\n");
            $_POST['composer'] = $newComposerId;
        } else {
            error_log("Failed to insert new composer: " . $_POST['new_composer'] . "\n");
        }
    }

    // --- Check Required Fields ---
    if ((empty($_POST['composer']) && empty($_POST['new_composer'])) ||
        (isset($_POST['composer']) && trim($_POST['composer']) === "" && empty($_POST['new_composer']))) {
        error_log("Composer not provided.\n");
        echo json_encode(['error' => 'Composer is required.']);
        exit;
    }
    if (!isset($_POST['piece']) || trim($_POST['piece']) === '') {
        error_log("Piece name not provided.\n");
        echo json_encode(['error' => 'Piece name is required.']);
        exit;
    }

    // --- Insert New Piece (metadata only) ---
    error_log("Inserting piece metadata...\n");
    $stmt = $pdo->prepare("INSERT INTO pieces (composer_id, name, info, catalogue_number) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([
        $_POST['composer'],
        $_POST['piece'],
        $_POST['piece_info'] ?? '',
        $_POST['catalogue_number'] ?? null
    ])) {
        $piece_id = $pdo->lastInsertId();
        error_log("Piece inserted with ID: $piece_id\n");
    } else {
        error_log("Failed to insert piece metadata.\n");
    }

    // --- Process Audio File Upload (main audio file) ---
    $audio_filename = basename($_FILES['audioFile']['name']);
    error_log("Uploading main audio file: $audio_filename\n");
    if (!move_uploaded_file($_FILES['audioFile']['tmp_name'], $target_dir . $audio_filename)) {
        error_log("Error uploading main audio file.\n");
        echo json_encode(['error' => 'Error uploading audio file.']);
        exit;
    }
    error_log("Main audio file uploaded successfully.\n");

    // --- Insert Recording Metadata (metadata only; no file info) ---
    error_log("Inserting recording metadata...\n");
    // Convert date from YYYY-MM-DD to DD-MM-YYYY.
    $recording_date = $_POST['recording_date'] ?? '';
    if ($recording_date != '') {
        $recording_date = date("d-m-Y", strtotime($recording_date));
    }
    $stmt = $pdo->prepare("INSERT INTO recordings (piece_id, recording_date, copyright_info, use_instrument_groups)
                           VALUES (?, ?, ?, ?)");
    if ($stmt->execute([
        $piece_id,
        $recording_date,
        $_POST['copyright_info'] ?? '',
        isset($_POST['use_instrument_groups']) ? 1 : 0
    ])) {
        $recording_id = $pdo->lastInsertId();
        error_log("Recording metadata inserted with ID: $recording_id\n");
    } else {
        error_log("Failed to insert recording metadata.\n");
    }

    // --- Process Instrument File Uploads ---
    $instrument_filenames = [];
    if (!empty($_FILES['instrument_files']['name'][0])) {
        error_log("Processing instrument file uploads...\n");
        foreach ($_FILES['instrument_files']['name'] as $index => $filename) {
            $target_file = $target_dir . basename($filename);
            if (move_uploaded_file($_FILES['instrument_files']['tmp_name'][$index], $target_file)) {
                $instrument_filenames[] = basename($target_file);
                error_log("Instrument file uploaded: " . basename($target_file) . "\n");
            } else {
                error_log("Failed to upload instrument file: $filename\n");
            }
        }
    }

    // --- Insert Instrument Usage Data into Database ---
    if (!empty($_POST['instrument_ids'])) {
        error_log("Inserting instrument usage data into database...\n");
        foreach ($_POST['instrument_ids'] as $index => $instrument_id) {
            $channel = $index + 3;  // Channels start at 3.
            $stmt = $pdo->prepare("INSERT INTO recording_instruments (recording_id, instrument_id, channel) VALUES (?, ?, ?)");
            if ($stmt->execute([$recording_id, $instrument_id, $channel])) {
                error_log("Inserted instrument (ID: $instrument_id) with channel $channel.\n");
            } else {
                error_log("Failed to insert instrument (ID: $instrument_id) with channel $channel.\n");
            }
        }
    }

    // --- Insert Custom Recording Groups ---
    if (!empty($_POST['custom_groups_json'])) {
        $groups = json_decode($_POST['custom_groups_json'], true);
        if (!empty($groups)) {
            error_log("Inserting custom recording groups...\n");
            foreach ($groups as $group) {
                $group_name = $group['group_name'];
                $stmt = $pdo->prepare("INSERT INTO recording_groups (recording_id, group_name) VALUES (?, ?)");
                if ($stmt->execute([$recording_id, $group_name])) {
                    $group_id = $pdo->lastInsertId();
                    error_log("Inserted custom group: '$group_name' (ID: $group_id)\n");
                    if (!empty($group['instruments'])) {
                        foreach ($group['instruments'] as $instrument_id) {
                            $stmt2 = $pdo->prepare("INSERT INTO recording_group_instruments (recording_group_id, instrument_id) VALUES (?, ?)");
                            if ($stmt2->execute([$group_id, $instrument_id])) {
                                error_log("Inserted custom group instrument: Group ID $group_id, Instrument ID $instrument_id\n");
                            } else {
                                error_log("Failed to insert custom group instrument: Group ID $group_id, Instrument ID $instrument_id\n");
                            }
                        }
                    }
                } else {
                    error_log("Failed to insert custom recording group: $group_name\n");
                }
            }
        }
    }

    // --- Forward File Information to the API Endpoints ---
    error_log("Forwarding file information to API endpoints...\n");

    // First, call the upload API endpoint.
    $upload_api_url = "https://audio.divisi-project.de/api/upload";
    $upload_post_data = [
        'filename' => $audio_filename
    ];
    error_log("Upload API payload: " . json_encode($upload_post_data) . "\n");
    $ch1 = curl_init($upload_api_url);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($upload_post_data));
    curl_setopt($ch1, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $upload_api_response = curl_exec($ch1);
    if (curl_errno($ch1)) {
        error_log("cURL error on upload API: " . curl_error($ch1) . "\n");
    }
    curl_close($ch1);
    error_log("Upload API response: " . $upload_api_response . "\n");

    // Next, call the process API endpoint.
    $process_api_url = "https://audio.divisi-project.de/api/process";
    $process_post_data = [
        'audio_filename'       => $audio_filename,
        'instrument_filenames' => $instrument_filenames,
        'channels'             => range(3, count($instrument_filenames) + 2)
    ];
    error_log("Process API payload: " . json_encode($process_post_data) . "\n");
    $ch2 = curl_init($process_api_url);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($process_post_data));
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $process_api_response = curl_exec($ch2);
    if (curl_errno($ch2)) {
        error_log("cURL error on process API: " . curl_error($ch2) . "\n");
    }
    curl_close($ch2);
    error_log("Process API response: " . $process_api_response . "\n");

    echo json_encode([
        'message' => 'Upload successful! Files have been forwarded to the API.',
        'upload_api_response' => $upload_api_response,
        'process_api_response' => $process_api_response
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Audio File</title>
    <!-- jQuery and jQuery UI -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Quill -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <style>
        /* Basic styles for the multi-page form */
        .form-page {
            display: none;
        }

        .form-page.active {
            display: block;
        }

        .nav-buttons {
            margin-top: 20px;
        }

        /* Instrument file upload items */
        .instrument-upload-item {
            padding: 5px;
            border: 1px solid #ccc;
            margin-bottom: 5px;
            cursor: move;
            background: #f9f9f9;
        }

        /* Custom recording groups */
        .custom-group {
            border: 1px solid #aaa;
            padding: 10px;
            margin-bottom: 10px;
            position: relative;
        }

        .custom-group button.remove-group {
            position: absolute;
            top: 5px;
            right: 5px;
        }

        .custom-group ul {
            list-style-type: none;
            margin: 0;
            padding: 5px;
            min-height: 30px;
            border: 1px dashed #ccc;
        }

        .custom-group li {
            padding: 5px;
            margin: 5px;
            border: 1px solid #ddd;
            background: #eee;
            cursor: move;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
        }

        #available-instruments-group {
            border: 1px dashed #ccc;
            min-height: 30px;
            padding: 5px;
            margin-bottom: 10px;
        }

        #available-instruments-group li {
            padding: 5px;
            margin: 5px;
            border: 1px solid #ddd;
            background: #eee;
            cursor: move;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
        }

        /* Required fields marker */
        label.required::after {
            content: " *";
            color: red;
        }

        /* New artist/composer rows */
        .new-artist-row,
        .new-composer-row {
            margin-bottom: 5px;
        }

        .new-artist-row input,
        .new-composer-row input {
            margin-right: 5px;
        }

        /* Select2 optgroup chevrons */
        .select2-results__group::after {
            content: "▼";
            float: right;
            margin-right: 5px;
        }

        .select2-results__group.collapsed::after {
            content: "►";
        }

        /* Quill editors */
        #piece-editor,
        #piece-info-editor {
            height: 60px;
            border: 1px solid #ccc;
            padding: 5px;
            margin-bottom: 10px;
        }

        /* Progress bar styles */
        #progressWrapper {
            width: 100%;
            background: #f3f3f3;
            border: 1px solid #ccc;
            margin-top: 10px;
            display: none;
        }

        #progressBar {
            width: 0%;
            height: 20px;
            background: #4caf50;
            text-align: center;
            color: white;
            line-height: 20px;
        }
    </style>
    <script>
        $(document).ready(function() {
            console.log("Document ready.");
            var currentPage = 1;

            // --- Initialize Quill editors ---
            var quillPiece = new Quill('#piece-editor', {
                theme: 'snow',
                modules: {
                    toolbar: ['italic']
                }
            });
            var quillInfo = new Quill('#piece-info-editor', {
                theme: 'snow',
                modules: {
                    toolbar: ['italic']
                }
            });
            console.log("Quill editors initialized.");

            // --- Initialize jQuery UI Datepicker for Recording Date ---
            // Replace the HTML5 date input with a text input and attach datepicker.
            $("input[name='recording_date']").datepicker({
                dateFormat: "dd-mm-yy"
            });

            // --- Pagination Functions ---
            function showPage(page) {
                console.log("Showing page " + page);
                $(".form-page").removeClass("active");
                $("#page" + page).addClass("active");
                currentPage = page;
            }

            function validatePage(page) {
                var valid = true;
                var errorMsg = "";
                if (page == 1) {
                    var artistSelected = $('#artist-select').val();
                    var newArtistExists = false;
                    $("#new-artists-container input[name='new_artists[]']").each(function() {
                        if ($(this).val().trim() !== "") {
                            newArtistExists = true;
                        }
                    });
                    if ((artistSelected == null || artistSelected.length === 0) && !newArtistExists) {
                        errorMsg += "Please select or add at least one artist.\n";
                        valid = false;
                    }
                    var composerSelected = $('#composer-select').val();
                    var newComposer = $("#new-composer-container input[name='new_composer']").val();
                    if ((composerSelected == null || composerSelected === "") && (!newComposer || newComposer.trim() === "")) {
                        errorMsg += "Please select or add a composer.\n";
                        valid = false;
                    }
                    if (quillPiece.getText().trim() === "") {
                        errorMsg += "Please enter a piece name.\n";
                        valid = false;
                    }
                    if ($("input[name='recording_date']").val().trim() === "") {
                        errorMsg += "Please enter a recording date.\n";
                        valid = false;
                    }
                    if ($("input[name='copyright_info']").val().trim() === "") {
                        errorMsg += "Please enter copyright info.\n";
                        valid = false;
                    }
                } else if (page == 2) {
                    if ($("input[name='audioFile']").val().trim() === "") {
                        errorMsg += "Please upload the main audio file.\n";
                        valid = false;
                    }
                    if ($('#instrument-select').val() == null || $('#instrument-select').val().length === 0) {
                        errorMsg += "Please select at least one instrument.\n";
                        valid = false;
                    }
                    $("#instrument-file-uploads input[type='file']").each(function() {
                        if ($(this).val().trim() === "") {
                            errorMsg += "Please upload a file for each selected instrument.\n";
                            valid = false;
                            return false;
                        }
                    });
                } else if (page == 3) {
                    $(".custom-group").each(function() {
                        var groupName = $(this).find(".group-name").val();
                        if ($.trim(groupName) === "") {
                            errorMsg += "Please enter a name for each custom group.\n";
                            valid = false;
                            return false;
                        }
                    });
                }
                if (!valid) {
                    alert(errorMsg);
                }
                return valid;
            }

            $(".next-btn").click(function() {
                if (validatePage(currentPage)) {
                    showPage(currentPage + 1);
                }
            });
            $(".prev-btn").click(function() {
                showPage(currentPage - 1);
            });

            // --- Initialize Select2 ---
            $('#artist-select').select2({
                placeholder: "Select artists",
                closeOnSelect: false
            });
            $('#composer-select').select2({
                placeholder: "Select composer",
                width: '300px',
                allowClear: true,
                minimumResultsForSearch: Infinity
            });
            $('#instrument-select').select2({
                placeholder: "Select instruments",
                closeOnSelect: false
            });
            console.log("Select2 initialized.");

            // --- Collapse Optgroups in Instruments Dropdown ---
            $('#instrument-select').on('select2:open', function() {
                setTimeout(function() {
                    $('.select2-results__group').each(function() {
                        var $group = $(this);
                        $group.css('cursor', 'pointer');
                        $group.nextUntil('.select2-results__group').hide();
                        $group.addClass('collapsed');
                        $group.off('click.collapsible').on('click.collapsible', function(e) {
                            e.stopPropagation();
                            $group.nextUntil('.select2-results__group').toggle();
                            $group.toggleClass('collapsed');
                        });
                    });
                }, 0);
            });

            // --- Update Instrument File Uploads ---
            function updateInstrumentFileUploads() {
                var selected = $('#instrument-select').select2('data');
                var container = $("#instrument-file-uploads");
                container.empty();
                $.each(selected, function(index, instrument) {
                    var item = $('<div class="instrument-upload-item"></div>');
                    item.append("<span>" + instrument.text + ":</span> ");
                    item.append("<input type='file' name='instrument_files[]' required>");
                    item.append("<input type='hidden' name='instrument_ids[]' value='" + instrument.id + "'>");
                    container.append(item);
                });
            }
            $('#instrument-select').on('change', function() {
                updateInstrumentFileUploads();
                updateAvailableInstrumentsGroup();
            });
            $("#instrument-file-uploads").sortable();
            updateInstrumentFileUploads();

            // --- Update Available Instruments for Custom Grouping ---
            function updateAvailableInstrumentsGroup() {
                var selected = $('#instrument-select').select2('data');
                var container = $("#available-instruments-group");
                container.empty();
                $.each(selected, function(index, instrument) {
                    var li = $("<li></li>").attr("data-id", instrument.id).text(instrument.text);
                    container.append(li);
                });
                container.sortable({
                    connectWith: ".custom-group-ul",
                    placeholder: "ui-state-highlight"
                }).disableSelection();
                // Also update custom groups when available instruments change.
                updateCustomGroups();
            }
            updateAvailableInstrumentsGroup();

            // --- Custom Recording Groups Functionality ---
            function updateCustomGroups() {
                // Serialize custom groups immediately
                var groups = [];
                $(".custom-group").each(function() {
                    var groupName = $(this).find(".group-name").val();
                    var instruments = [];
                    $(this).find("ul li").each(function() {
                        instruments.push($(this).attr("data-id"));
                    });
                    groups.push({
                        group_name: groupName,
                        instruments: instruments
                    });
                });
                $("#custom_groups_json").val(JSON.stringify(groups));
                console.log("Custom groups updated: " + $("#custom_groups_json").val());
            }

            $("#add-custom-group").on("click", function() {
                var groupDiv = $('<div class="custom-group"></div>');
                groupDiv.append('<label class="required">Group Name:</label> <input type="text" class="group-name" placeholder="Group Name" required>');
                groupDiv.append(' <button type="button" class="remove-group">Remove Group</button>');
                var ul = $('<ul class="custom-group-ul"></ul>');
                ul.sortable({
                    connectWith: "#available-instruments-group, .custom-group-ul",
                    placeholder: "ui-state-highlight",
                    update: function() {
                        updateCustomGroups();
                    }
                }).disableSelection();
                groupDiv.append(ul);
                $("#custom-groups-container").append(groupDiv);
                updateCustomGroups();
            });
            $("#custom-groups-container").on("click", ".remove-group", function() {
                $(this).closest(".custom-group").remove();
                updateCustomGroups();
            });

            // Also update custom groups whenever an instrument is dropped in a group.
            $("#available-instruments-group, .custom-group-ul").sortable({
                connectWith: ".custom-group-ul, #available-instruments-group",
                placeholder: "ui-state-highlight",
                update: function() {
                    updateCustomGroups();
                }
            }).disableSelection();

            // --- New Artist Functionality ---
            $("#new-artists-container").hide();
            $("#toggle-new-artist").click(function() {
                $("#new-artists-container").toggle();
                if ($("#new-artists-container").is(":visible") && $("#new-artists-container .new-artist-row").length === 0) {
                    $("#new-artists-container").append(getNewArtistRow());
                }
            });

            function getNewArtistRow() {
                return '<div class="new-artist-row">' +
                    '<input type="text" name="new_artists[]" placeholder="New Artist Name" required>' +
                    '<input type="text" name="artist_infos[]" placeholder="Artist Info">' +
                    ' <button type="button" class="remove-artist">Remove</button>' +
                    '</div>';
            }
            $("#new-artists-container").prepend('<button type="button" id="add-new-artist-row">Add New Artist Row</button><br>');
            $("#new-artists-container").on("click", "#add-new-artist-row", function() {
                $("#new-artists-container").append(getNewArtistRow());
            });
            $("#new-artists-container").on("click", ".remove-artist", function() {
                $(this).parent().remove();
            });

            // --- New Composer Functionality ---
            $("#new-composer-container").hide();
            $("#toggle-new-composer").click(function() {
                $("#new-composer-container").toggle();
                if ($("#new-composer-container").children().length === 0) {
                    $("#new-composer-container").append('<div class="new-composer-row">' +
                        '<input type="text" name="new_composer" placeholder="New Composer Name" required>' +
                        '<input type="text" name="composer_info" placeholder="Composer Info">' +
                        ' <button type="button" id="remove-new-composer">Remove</button>' +
                        '</div>');
                }
            });
            $("#new-composer-container").on("click", "#remove-new-composer", function() {
                $(this).parent().remove();
            });

            // --- AJAX Form Submission with FormData ---
            $("#uploadForm").on("submit", function(e) {
                e.preventDefault();

                // Update hidden inputs from Quill editors.
                var pieceHtml = quillPiece.root.innerHTML.trim()
                    .replace(/<\/?p>/g, '')
                    .replace(/<em>/g, '<i>')
                    .replace(/<\/em>/g, '</i>');
                $("#piece-hidden").val(pieceHtml);

                var pieceInfoHtml = quillInfo.root.innerHTML.trim()
                    .replace(/<\/?p>/g, '')
                    .replace(/<em>/g, '<i>')
                    .replace(/<\/em>/g, '</i>');
                $("#piece-info-hidden").val(pieceInfoHtml);

                // If a new composer is provided, clear the composer select.
                var newComposer = $("#new-composer-container input[name='new_composer']").val();
                if (newComposer && newComposer.trim() !== "") {
                    $("#composer-select").val('');
                }

                updateCustomGroups();

                // Build FormData from the form.
                var formData = new FormData(this);

                // Debug: log all FormData entries.
                for (var pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }

                // Show progress bar.
                $("#progressWrapper").show();
                $("#progressBar").width("0%");
                $("#progressBar").text("0%");

                // Use the fetch API to post data.
                fetch("<?php echo $_SERVER['PHP_SELF']; ?>", {
                    method: "POST",
                    body: formData,
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    $("#progressBar").text("Upload complete");
                    alert("Submit successful: " + data.message + "\nAPI Responses:\nUpload: " +
                          data.upload_api_response + "\nProcess: " + data.process_api_response);
                })
                .catch(function(error) {
                    console.error("Submission error:", error);
                    alert("An error occurred during submission.");
                });
            });
        });
    </script>
</head>

<body>
    <h2>Upload an Audio File</h2>

    <!-- Progress Bar -->
    <div id="progressWrapper">
        <div id="progressBar">0%</div>
    </div>

    <!-- The form with id="uploadForm" for AJAX submission -->
    <form id="uploadForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" enctype="multipart/form-data" autocomplete="off">
        <!-- Hidden fields inside the form -->
        <input type="hidden" name="custom_groups_json" id="custom_groups_json" value="">

        <!-- PAGE 1: Basic Information -->
        <div class="form-page active" id="page1">
            <h3>Step 1: Basic Information</h3>
            <!-- Existing Artists (Select2) -->
            <label class="required"><strong>Artists:</strong></label><br>
            <select id="artist-select" name="artists[]" multiple="multiple" style="width: 300px;" required>
                <?php foreach ($artists as $artist): ?>
                    <option value="<?= htmlspecialchars($artist['id']) ?>"><?= htmlspecialchars($artist['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <br><br>
            <!-- New Artist Section -->
            <button type="button" id="toggle-new-artist">Add New Artist</button>
            <div id="new-artists-container"></div>
            <br><br>
            <!-- Existing Composer (Select2) -->
            <label class="required"><strong>Composer:</strong></label><br>
            <select id="composer-select" name="composer" style="width: 300px;">
                <option value="">-- Select Composer --</option>
                <?php foreach ($composers as $composer): ?>
                    <option value="<?= htmlspecialchars($composer['id']) ?>"><?= htmlspecialchars($composer['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <br><br>
            <!-- New Composer Section -->
            <button type="button" id="toggle-new-composer">Add New Composer</button>
            <div id="new-composer-container"></div>
            <br><br>
            <!-- Piece Name -->
            <label class="required"><strong>Piece Name:</strong></label><br>
            <div id="piece-editor"></div>
            <input type="hidden" name="piece" id="piece-hidden" required>
            <br><br>
            <!-- Piece Info -->
            <label class="required"><strong>Piece Info:</strong></label><br>
            <div id="piece-info-editor"></div>
            <input type="hidden" name="piece_info" id="piece-info-hidden">
            <br>
            <label>Catalogue Number:</label>
            <input type="text" name="catalogue_number">
            <br><br>
            <!-- Recording Details -->
            <label class="required"><strong>Recording Date:</strong></label>
            <!-- Change type from date to text and attach jQuery UI datepicker -->
            <input type="text" name="recording_date" required>
            <br>
            <label class="required"><strong>Copyright Info:</strong></label>
            <input type="text" name="copyright_info" required>
            <br><br>
            <div class="nav-buttons">
                <button type="button" class="next-btn">Next &raquo;</button>
            </div>
        </div>

        <!-- PAGE 2: Audio File and Instruments -->
        <div class="form-page" id="page2">
            <h3>Step 2: Audio File and Instruments</h3>
            <label class="required"><strong>Upload Audio File:</strong></label>
            <input type="file" name="audioFile" required>
            <br><br>
            <label class="required"><strong>Instruments:</strong></label><br>
            <select id="instrument-select" name="selected_instruments[]" multiple="multiple" style="width: 300px;" required>
                <?php
                foreach ($instrument_groups as $group) {
                    echo '<optgroup label="' . htmlspecialchars($group['name']) . '">';
                    foreach ($instruments as $instrument) {
                        if ($instrument['instrument_group_id'] == $group['id']) {
                            echo '<option value="' . htmlspecialchars($instrument['id']) . '">' . htmlspecialchars($instrument['name']) . '</option>';
                        }
                    }
                    echo '</optgroup>';
                }
                ?>
            </select>
            <br><br>
            <label class="required"><strong>Upload Instrument Files:</strong></label>
            <div id="instrument-file-uploads" style="border: 1px dashed #aaa; padding: 10px;">
                <!-- Dynamically populated -->
            </div>
            <br>
            <div class="nav-buttons">
                <button type="button" class="prev-btn">&laquo; Previous</button>
                <button type="button" class="next-btn">Next &raquo;</button>
            </div>
        </div>

        <!-- PAGE 3: Custom Recording Groups and Submit -->
        <div class="form-page" id="page3">
            <h3>Step 3: Custom Recording Groups</h3>
            <button type="button" id="add-custom-group">Add Custom Group</button>
            <div id="custom-groups-container">
                <!-- Custom groups will appear here -->
            </div>
            <br>
            <label><strong>Available Instruments for Grouping:</strong></label>
            <ul id="available-instruments-group">
                <!-- Dynamically populated -->
            </ul>
            <br>
            <div class="nav-buttons">
                <button type="button" class="prev-btn">&laquo; Previous</button>
                <button type="submit">Submit</button>
            </div>
        </div>
    </form>
</body>

</html>