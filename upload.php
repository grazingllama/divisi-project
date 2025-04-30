<?php
$jsLogs = [];

function console_log($message) {
    global $jsLogs;
    $jsLogs[] = $message;
}

require 'config.php';

// Ensure $pdo is set correctly.
$database = new Database();
$pdo = $database->getConnection();
if (!$pdo) {
    console_log("Database connection error.\n");
    die("Database connection error.");
}

function handleUpload($fileKey, $targetDir = 'uploads/') {
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    if (!empty($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === 0) {
        $filename = basename($_FILES[$fileKey]['name']);
        $targetFile = $targetDir . $filename;
        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetFile)) {
            return $filename;
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    console_log("Upload POST received.");
    
    // Check if key fields are empty; if so, ignore this submission.
    if (empty($_POST['piece_name']) && empty($_POST['composer']) && empty($_POST['new_composer'])) {
        console_log("Empty submission detected, ignoring.");
        header('Content-Type: application/json');
        echo json_encode(['logs' => $jsLogs, 'html' => 'Empty submission ignored.']);
        exit;
    }
    
    // Debug incoming data
    console_log("POST data received:");
    console_log("artists: " . print_r($_POST['artists'], true));
    console_log("new_artists: " . print_r($_POST['new_artists'] ?? [], true));
    console_log("composer: " . $_POST['composer']);
    console_log("new_composer: " . $_POST['new_composer']);
    console_log("piece_name: " . $_POST['piece_name']);
    console_log("piece_info: " . $_POST['piece_info']);
    console_log("catalogue_number: " . $_POST['catalogue_number']);
    console_log("selected_instruments_order: " . $_POST['selected_instruments_order']);
    console_log("custom_groups_json: " . $_POST['custom_groups_json']);
    
    // Debug files
    console_log("FILES data received:");
    console_log(print_r($_FILES, true));
    
    // --- Process form fields ---
    $artists = isset($_POST['artists']) ? $_POST['artists'] : [];
    console_log("Artists from select: " . print_r($artists, true));
    if (isset($_POST['new_artists']) && is_array($_POST['new_artists'])) {
        foreach ($_POST['new_artists'] as $newArtist) {
            $newArtist = trim($newArtist);
            if (!empty($newArtist)) {
                $artists[] = $newArtist;
            }
        }
    }
    console_log("Processed Artists: " . print_r($artists, true));
    
    $composer = trim($_POST['composer'] ?? '');
    $newComposer = trim($_POST['new_composer'] ?? '');
    $composer = $composer ?: $newComposer;
    console_log("Composer: " . $composer);
    
    $pieceName = trim($_POST['piece_name'] ?? '');
    $pieceInfo = trim($_POST['piece_info'] ?? '');
    $catalogueNumber = trim($_POST['catalogue_number'] ?? '');
    
    $recordingYear = trim($_POST['recording_year'] ?? '');
    $recordingCopyright = trim($_POST['recording_copyright'] ?? '');
    console_log("Piece: {$pieceName}, Info: {$pieceInfo}, Catalogue: {$catalogueNumber}");
    console_log("Recording Year: {$recordingYear}, Copyright: {$recordingCopyright}");
    
    $instrumentGroups = isset($_POST['instrument_groups']) ? $_POST['instrument_groups'] : [];
    console_log("Instrument Groups: " . print_r($instrumentGroups, true));
    
    $customGroupsJson = $_POST['custom_groups_json'] ?? '';
    $customGroupsData = json_decode($customGroupsJson, true);
    console_log("Custom Groups JSON: $customGroupsJson");
    
    $useStandardInstrumentGroups = isset($_POST['use_standard_instrument_groups']) ? 1 : 0;
    console_log("Use standard instrument groups: " . $useStandardInstrumentGroups);
    
    // --- Process file uploads ---
    $mainAudioFilename = handleUpload('main_audio');
    console_log("Main audio filename: " . print_r($mainAudioFilename, true));
    
    $instrumentAudioFiles = [];
    if (isset($_FILES['instrument_audio'])) {
        $files = $_FILES['instrument_audio'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === 0) {
                $filename = basename($files['name'][$i]);
                $targetDir = 'uploads/';
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                $targetFile = $targetDir . $filename;
                if (move_uploaded_file($files['tmp_name'][$i], $targetFile)) {
                    $instrumentAudioFiles[] = $filename;
                    console_log("Uploaded instrument file: " . $filename);
                } else {
                    console_log("Failed to move uploaded file: " . $filename);
                }
            } else {
                console_log("Error uploading file: " . $filename . " Error code: " . $files['error'][$i]);
            }
        }
    }
    
    // --- Database insertion ---
    try {
        $pdo->beginTransaction();
        
        // 1. Process Composer:
        if ($newComposer) {
            $stmt = $pdo->prepare("INSERT INTO composers (name, info, img) VALUES (?, '', '')");
            $stmt->execute([$newComposer]);
            $composer_id = $pdo->lastInsertId();
            console_log("Inserted new composer '{$newComposer}' with ID: " . $composer_id);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM composers WHERE name=?");
            $stmt->execute([$composer]);
            $composerRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $composer_id = $composerRow ? $composerRow['id'] : null;
            if (!$composer_id) {
                $stmt = $pdo->prepare("INSERT INTO composers (name, info, img) VALUES (?, '', '')");
                $stmt->execute([$composer]);
                $composer_id = $pdo->lastInsertId();
                console_log("Inserted composer '{$composer}' with ID: " . $composer_id);
            } else {
                console_log("Found existing composer '{$composer}' with ID: " . $composer_id);
            }
        }
        
        // 2. Insert Piece:
        $stmt = $pdo->prepare("INSERT INTO pieces (composer_id, name, info, catalogue_number) VALUES (?,?,?,?)");
        $stmt->execute([$composer_id, $pieceName, $pieceInfo, $catalogueNumber]);
        $piece_id = $pdo->lastInsertId();
        console_log("Inserted piece '{$pieceName}' with ID: " . $piece_id);
        
        // 3. Insert Recording:
        $recordingDate = $recordingYear ? $recordingYear . '-01-01' : null;
        $defaultImg = '';
        $useInstrumentGroups = ($useStandardInstrumentGroups || (!empty($customGroupsData) && is_array($customGroupsData))) ? 1 : 0;
        $stmt = $pdo->prepare("INSERT INTO recordings 
            (piece_id, recording_date, complete_recording, img, copyright_info, use_instrument_groups)
            VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $piece_id,
            $recordingDate,
            $mainAudioFilename,
            $defaultImg,
            $recordingCopyright,
            $useInstrumentGroups
        ]);
        $recordingID = $pdo->lastInsertId();
        console_log("Inserted recording with ID: " . $recordingID);
        
        // 4. Insert Recording Artists:
        foreach ($artists as $artistName) {
            $stmt = $pdo->prepare("SELECT id FROM artists WHERE name=?");
            $stmt->execute([$artistName]);
            $artistRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($artistRow) {
                $artist_id = $artistRow['id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO artists (name, info, img) VALUES (?, '', '')");
                $stmt->execute([$artistName]);
                $artist_id = $pdo->lastInsertId();
                console_log("Inserted new artist '{$artistName}' with ID: " . $artist_id);
            }
            $stmt = $pdo->prepare("INSERT INTO recording_artists (recording_id, artist_id) VALUES (?,?)");
            $stmt->execute([$recordingID, $artist_id]);
        }
        console_log("Inserted recording artists.");
        
        // 5. Insert Recording Instruments:
        $selectedInstrumentsOrder = $_POST['selected_instruments_order'] ?? '';
        if (!empty($selectedInstrumentsOrder)) {
            $instrumentIds = explode(',', $selectedInstrumentsOrder);
            $channel = 3;
            foreach ($instrumentIds as $instrId) {
                $stmt = $pdo->prepare("INSERT INTO recording_instruments (recording_id, instrument_id, channel) VALUES (?,?,?)");
                $stmt->execute([$recordingID, $instrId, $channel]);
                console_log("Inserted recording instrument. Recording: {$recordingID}, Instrument: {$instrId}, Channel: " . $channel);
                $channel++;
            }
        }
        
        // 6. Insert Custom Recording Groups and Their Instruments:
        $groupOrder = 1;
        if (!empty($customGroupsData) && is_array($customGroupsData)) {
            foreach ($customGroupsData as $group) {
                $stmt = $pdo->prepare("INSERT INTO recording_groups (recording_id, name, `order`) VALUES (?,?,?)");
                $stmt->execute([$recordingID, $group['name'], $groupOrder]);
                $recordingGroupID = $pdo->lastInsertId();
                console_log("Inserted custom recording group '{$group['name']}' with ID: " . $recordingGroupID);
                $groupOrder++;
                if (!empty($group['instruments']) && is_array($group['instruments'])) {
                    $memberOrder = 1;
                    foreach ($group['instruments'] as $instrument) {
                        $stmt = $pdo->prepare("INSERT INTO recording_group_members (recording_group_id, instrument_id, `order`) VALUES (?,?,?)");
                        $stmt->execute([$recordingGroupID, $instrument['id'], $memberOrder]);
                        console_log("Inserted group member: Instrument ID " . $instrument['id'] . " into group ID: " . $recordingGroupID);
                        $memberOrder++;
                    }
                }
            }
        } elseif ($useStandardInstrumentGroups) {
            foreach ($instrumentGroups as $grpName) {
                $grpName = trim($grpName);
                if (!$grpName) continue;
                $stmt = $pdo->prepare("INSERT INTO recording_groups (recording_id, name, `order`) VALUES (?,?,?)");
                $stmt->execute([$recordingID, $grpName, $groupOrder]);
                console_log("Inserted standard recording group '{$grpName}' with order: " . $groupOrder);
                $groupOrder++;
            }
        }
        
        $pdo->commit();
        console_log("Database transaction committed successfully.");
        
        $response = [
            'logs' => $jsLogs,
            'html' => "Upload complete. Recording ID: " . $recordingID
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        console_log("Database error: " . $e->getMessage());
        
        $response = [
            'logs' => $jsLogs,
            'html' => "Database error: " . htmlspecialchars($e->getMessage())
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    exit;
}

// Fetch data for form display.
$artistsRes = $pdo->query("SELECT id, name FROM artists ORDER BY name");
$artists = $artistsRes ? $artistsRes->fetchAll(PDO::FETCH_ASSOC) : [];

$composersRes = $pdo->query("SELECT id, name FROM composers ORDER BY name");
$composers = $composersRes ? $composersRes->fetchAll(PDO::FETCH_ASSOC) : [];

$groupsRes = $pdo->query("SELECT id, name FROM instrument_groups ORDER BY id");
$instrument_groups = $groupsRes ? $groupsRes->fetchAll(PDO::FETCH_ASSOC) : [];
$availableInstruments = [];
foreach ($instrument_groups as $group) {
    $stmt = $pdo->prepare("SELECT id, name FROM instruments WHERE instrument_group_id = ? ORDER BY id");
    $stmt->execute([$group['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $instr) {
        $instr['group'] = $group['name']; 
        $availableInstruments[] = $instr;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Recording Upload</title>
    <style>
        /* Simple styles for progress and lists */
        #progressBar {
            width: 100%;
            background-color: #ddd;
        }
        #progress {
            width: 0%;
            height: 20px;
            background-color: #4caf50;
        }
        .instrument-item, .group-item {
            padding: 5px;
            border: 1px solid #ccc;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .instrument-controls button,
        .group-controls button {
            margin: 0 2px;
        }
        .custom-group {
            border: 1px dashed #666;
            margin: 10px 0;
            padding: 5px;
        }
        .group-dropzone {
            min-height: 30px;
            border: 1px dotted #aaa;
            padding: 5px;
        }
    </style>
</head>
<body>
    <h1>Upload Recording</h1>
    <form id="uploadForm" action="upload.php" method="POST" enctype="multipart/form-data">
        <fieldset>
            <legend>Recording Details</legend>
            <label for="artists">Artists (select multiple):</label><br>
            <select name="artists[]" id="artists" multiple>
                <?php foreach($artists as $artist): ?>
                    <option value="<?= htmlspecialchars($artist['name']) ?>"><?= htmlspecialchars($artist['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <br>
            <label>Or Add New Artists:</label>
            <div id="newArtistsContainer">
                <input type="text" name="new_artists[]" placeholder="New Artist">
            </div>
            <button type="button" onclick="addNewArtistInput()">Add Another Artist</button>
            <br><br>
            <label for="composer">Composer (select):</label>
            <select name="composer" id="composer">
                <option value="">--Select--</option>
                <?php foreach($composers as $composer): ?>
                    <option value="<?= htmlspecialchars($composer['name']) ?>"><?= htmlspecialchars($composer['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <br>
            <label for="new_composer">Or Add New Composer:</label>
            <input type="text" id="new_composer" name="new_composer"><br><br>
            <label for="piece_name">Piece Name (use <i>&lt;i&gt;</i> for italic formatting):</label>
            <input type="text" id="piece_name" name="piece_name"><br><br>
            <label for="piece_info">Piece Info (use <i>&lt;i&gt;</i> for italic formatting):</label>
            <textarea id="piece_info" name="piece_info"></textarea><br><br>
            <label for="catalogue_number">Catalogue Number:</label>
            <input type="text" id="catalogue_number" name="catalogue_number"><br><br>
            <label for="recording_year">Recording Year:</label>
            <input type="number" id="recording_year" name="recording_year" min="1900" max="2100"><br><br>
            <label for="recording_copyright">Recording Copyright Information:</label>
            <input type="text" id="recording_copyright" name="recording_copyright"><br><br>
        </fieldset>
        <fieldset>
            <legend>Available Instruments</legend>
            <?php foreach($instrument_groups as $group): ?>
                <details>
                    <summary><?= htmlspecialchars($group['name']) ?></summary>
                    <?php 
                        $stmt = $pdo->prepare("SELECT id, name FROM instruments WHERE instrument_group_id = ? ORDER BY id");
                        $stmt->execute([$group['id']]);
                        $instrumentsInGroup = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php foreach($instrumentsInGroup as $instr): ?>
                        <div class="instrument-item">
                            <label>
                                <input type="checkbox" draggable="true"
                                    ondragstart="dragStart(event, '<?= $instr['id'] ?>', '<?= htmlspecialchars($instr['name']) ?>', '<?= htmlspecialchars($group['name']) ?>')"
                                    onchange="toggleInstrument(this, '<?= $instr['id'] ?>', '<?= htmlspecialchars($instr['name']) ?>', '<?= htmlspecialchars($group['name']) ?>')" />
                                <?= htmlspecialchars($instr['name']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
        </fieldset>
        <fieldset>
            <legend>Selected Instruments &amp; Uploads</legend>
            <div id="selectedInstruments"></div>
            <input type="hidden" name="selected_instruments_order" id="selected_instruments_order" />
        </fieldset>
        <fieldset>
            <legend>Custom Recording Groups</legend>
            <div id="customGroupsContainer"></div>
            <input type="text" id="new_group_name" placeholder="Group Name">
            <button type="button" onclick="addCustomGroup()">Add Custom Group</button>
            <input type="hidden" name="custom_groups_json" id="custom_groups_json" />
        </fieldset>
        <fieldset>
            <legend>Audio Files</legend>
            <label for="main_audio">Main Audio File:</label>
            <input type="file" id="main_audio" name="main_audio" accept="audio/*" required><br><br>
        </fieldset>
        <button type="submit">Submit</button>
    </form>
    <div id="progressBar">
        <div id="progress"></div>
    </div>
    <script>
        var selectedInstruments = [];
        function toggleInstrument(checkbox, id, name, group) {
            if (checkbox.checked) {
                addInstrument(id, name, group);
            } else {
                removeInstrument(id);
            }
            updateSelectedInstrumentsOrder();
        }
        function addInstrument(id, name, group) {
            if (selectedInstruments.find(item => item.id === id)) return;
            selectedInstruments.push({id: id, name: name, group: group});
            renderSelectedInstruments();
        }
        function removeInstrument(id) {
            selectedInstruments = selectedInstruments.filter(item => item.id !== id);
            renderSelectedInstruments();
        }
        function moveUp(index) {
            if(index <= 0) return;
            [selectedInstruments[index - 1], selectedInstruments[index]] = [selectedInstruments[index], selectedInstruments[index - 1]];
            renderSelectedInstruments();
        }
        function moveDown(index) {
            if(index >= selectedInstruments.length - 1) return;
            [selectedInstruments[index + 1], selectedInstruments[index]] = [selectedInstruments[index], selectedInstruments[index + 1]];
            renderSelectedInstruments();
        }
        function renderSelectedInstruments() {
            var container = document.getElementById('selectedInstruments');
            container.innerHTML = '';
            selectedInstruments.forEach(function(item, index) {
                var div = document.createElement('div');
                div.className = 'instrument-item';
                div.draggable = true;
                div.ondragstart = function(ev) {
                    ev.dataTransfer.setData("application/json", JSON.stringify(item));
                };
                div.innerHTML = '<span>' + item.name + '</span>' +
                    '<div class="instrument-controls">' +
                    '<button type="button" onclick="moveUp(' + index + ')">&#9650;</button>' +
                    '<button type="button" onclick="moveDown(' + index + ')">&#9660;</button>' +
                    '<input type="file" name="instrument_audio[]" accept="audio/*" style="margin-left:10px;">' +
                    '<button type="button" onclick="removeInstrument(\'' + item.id + '\')">Remove</button>' +
                    '</div>';
                container.appendChild(div);
            });
            updateSelectedInstrumentsOrder();
        }
        function updateSelectedInstrumentsOrder() {
            document.getElementById('selected_instruments_order').value = selectedInstruments.map(function(item){ return item.id; }).join(',');
        }
        var customGroups = [];
        function addCustomGroup() {
            var groupName = document.getElementById('new_group_name').value.trim();
            if (!groupName) return;
            customGroups.push({ name: groupName, instruments: [] });
            document.getElementById('new_group_name').value = '';
            renderCustomGroups();
        }
        function renderCustomGroups() {
            var container = document.getElementById('customGroupsContainer');
            container.innerHTML = '';
            customGroups.forEach(function(group, gIndex) {
                var groupDiv = document.createElement('div');
                groupDiv.className = 'custom-group';
                groupDiv.innerHTML = '<div style="display:flex; justify-content:space-between; align-items:center;">' +
                    '<strong>' + group.name + '</strong>' +
                    '<div class="group-header-controls">' +
                    '<button type="button" onclick="moveCustomGroupUp(' + gIndex + ')">&#9650;</button>' +
                    '<button type="button" onclick="moveCustomGroupDown(' + gIndex + ')">&#9660;</button>' +
                    '<button type="button" onclick="removeCustomGroup(' + gIndex + ')">Remove Group</button>' +
                    '</div></div>';
                var dropzone = document.createElement('div');
                dropzone.className = 'group-dropzone';
                dropzone.setAttribute('ondragover', 'event.preventDefault()');
                dropzone.setAttribute('ondrop', 'dropInstrument(event, ' + gIndex + ')');
                dropzone.innerHTML = 'Drop instruments here';
                groupDiv.appendChild(dropzone);
                group.instruments.forEach(function(item, index) {
                    var itemDiv = document.createElement('div');
                    itemDiv.className = 'group-item';
                    itemDiv.draggable = true;
                    itemDiv.ondragstart = function(ev){ 
                        ev.dataTransfer.setData("application/json", JSON.stringify({
                            id: item.id, name: item.name, group: item.group
                        })); 
                    };
                    itemDiv.innerHTML = '<span>' + item.name + ' (' + item.group + ')</span>' +
                        '<div class="group-controls">' +
                        '<button type="button" onclick="moveGroupItemUp(' + gIndex + ', ' + index + ')">&#9650;</button>' +
                        '<button type="button" onclick="moveGroupItemDown(' + gIndex + ', ' + index + ')">&#9660;</button>' +
                        '<button type="button" onclick="removeInstrumentFromGroup(' + gIndex + ', ' + index + ')">Remove</button>' +
                        '</div>';
                    groupDiv.appendChild(itemDiv);
                });
                container.appendChild(groupDiv);
            });
            document.getElementById('custom_groups_json').value = JSON.stringify(customGroups);
        }
        function dropInstrument(event, groupIndex) {
            event.preventDefault();
            var data = JSON.parse(event.dataTransfer.getData("application/json"));
            if (!customGroups[groupIndex].instruments.some(x => x.id === data.id)) {
                customGroups[groupIndex].instruments.push(data);
            }
            renderCustomGroups();
        }
        function moveGroupItemUp(groupIndex, itemIndex) {
            var group = customGroups[groupIndex];
            if (itemIndex <= 0) return;
            [group.instruments[itemIndex - 1], group.instruments[itemIndex]] =
                [group.instruments[itemIndex], group.instruments[itemIndex - 1]];
            renderCustomGroups();
        }
        function moveGroupItemDown(groupIndex, itemIndex) {
            var group = customGroups[groupIndex];
            if (itemIndex >= group.instruments.length - 1) return;
            [group.instruments[itemIndex + 1], group.instruments[itemIndex]] =
                [group.instruments[itemIndex], group.instruments[itemIndex + 1]];
            renderCustomGroups();
        }
        function removeInstrumentFromGroup(groupIndex, itemIndex) {
            customGroups[groupIndex].instruments.splice(itemIndex, 1);
            renderCustomGroups();
        }
        function removeCustomGroup(groupIndex) {
            customGroups.splice(groupIndex, 1);
            renderCustomGroups();
        }
        function moveCustomGroupUp(groupIndex) {
            if (groupIndex <= 0) return;
            [customGroups[groupIndex - 1], customGroups[groupIndex]] =
              [customGroups[groupIndex], customGroups[groupIndex - 1]];
            renderCustomGroups();
        }
        function moveCustomGroupDown(groupIndex) {
            if (groupIndex >= customGroups.length - 1) return;
            [customGroups[groupIndex + 1], customGroups[groupIndex]] =
              [customGroups[groupIndex], customGroups[groupIndex + 1]];
            renderCustomGroups();
        }
        document.getElementById('selectedInstruments').addEventListener('dragstart', function(ev) {});
        function addNewArtistInput() {
            var container = document.getElementById('newArtistsContainer');
            var input = document.createElement('input');
            input.type = 'text';
            input.name = 'new_artists[]';
            input.placeholder = 'New Artist';
            container.appendChild(document.createElement('br'));
            container.appendChild(input);
        }
    </script>
    <script>
        // Override form submission to use AJAX and disable duplicate submissions.
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // Disable the submit button to prevent double submission.
            var submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            
            // Update hidden fields before submission.
            document.getElementById('selected_instruments_order').value = 
                selectedInstruments.map(item => item.id).join(',');
            document.getElementById('custom_groups_json').value = JSON.stringify(customGroups);
            
            var formData = new FormData(this);
            
            // Override composer field: if a new composer is provided, clear the selected composer.
            var selectedComposer = document.getElementById('composer').value;
            var newComposer = document.getElementById('new_composer').value.trim();
            if (newComposer) {
                formData.set('new_composer', newComposer);
                formData.set('composer', '');
            } else {
                formData.set('composer', selectedComposer);
                formData.set('new_composer', '');
            }
            
            // Handle artists: clear and re-append.
            formData.delete('artists[]');
            var selectedArtists = Array.from(document.getElementById('artists').selectedOptions)
                .map(option => option.value);
            selectedArtists.forEach(function(artist) {
                formData.append('artists[]', artist);
            });
            formData.delete('new_artists[]');
            document.querySelectorAll('input[name="new_artists[]"]').forEach(function(input) {
                if (input.value.trim()) {
                    formData.append('new_artists[]', input.value.trim());
                }
            });
            
            // Set other fields explicitly.
            formData.set('piece_name', document.getElementById('piece_name').value);
            formData.set('piece_info', document.getElementById('piece_info').value);
            formData.set('catalogue_number', document.getElementById('catalogue_number').value);
            formData.set('recording_year', document.getElementById('recording_year').value);
            formData.set('recording_copyright', document.getElementById('recording_copyright').value);
            
            console.log('Sending form data:');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload.php', true);
            
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    var percent = (e.loaded / e.total) * 100;
                    document.getElementById('progress').style.width = percent + '%';
                }
            };
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        console.log('Server response:', response);
                        if (response.logs) {
                            response.logs.forEach(msg => console.log(msg));
                        }
                        if (response.html) {
                            var messageDiv = document.createElement('div');
                            messageDiv.innerHTML = response.html;
                            document.getElementById('uploadForm').insertAdjacentElement('beforebegin', messageDiv);
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        console.error('Raw response:', xhr.responseText);
                    }
                } else {
                    console.error('Upload failed:', xhr.status);
                    alert('Upload failed. Check console for details.');
                }
            };
            
            xhr.onerror = function() {
                console.error('Upload failed');
                alert('Upload failed. Check console for details.');
            };
            
            xhr.send(formData);
        });
    </script>
    <script>
        (function(){
            var logs = <?= json_encode($jsLogs) ?>;
            logs.forEach(function(msg) {
                console.log(msg);
            });
        })();
    </script>
</body>
</html>
