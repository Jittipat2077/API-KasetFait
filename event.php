<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/list-date_event', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];
    
    // Set MySQL session to use Thai locale for date formatting
    $conn->query("SET lc_time_names = 'th_TH'");

    $sql = 'SELECT 
    	name_festival,
        status_open_close,
        img_mapzone,
                DATE_FORMAT(date_start_reservation, "%d %M %Y") AS date_start_reservation,
                DATE_FORMAT(date_end_reservation, "%d %M %Y") AS date_end_reservation,
                DATE_FORMAT(date_start_festival, "%d %M %Y") AS date_start_festival,
                DATE_FORMAT(date_end_festival, "%d %M %Y") AS date_end_festival,
                DATE_FORMAT(date_start_payment, "%d %M %Y") AS date_start_payment,
                DATE_FORMAT(date_end_payment, "%d %M %Y") AS date_end_payment
            FROM date_event';
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();
    while ($row = $result->fetch_assoc()) {
        array_push($data, $row);
    }

    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});


$app->get('/get-event', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];
    $sql = 'SELECT * FROM date_event';
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();
    foreach ($result as $row) {
        array_push($data, $row);
    }

    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});


$app->post('/update-zone-event-zone', function (Request $request, Response $response, $args) {
    $json = $request->getBody();
    $jsonData = json_decode($json, true);
    $conn = $GLOBALS['conn'];

    foreach ($jsonData as $event) {
        $date_event = $event['id_date']; // ดึงค่า id_reservation จากข้อมูล

        // ทำการอัปเดตทุกแถวในตาราง zone
        $sql = "UPDATE zone SET id_date=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $date_event); // สมมติว่า 'id_reservation' เป็นชนิดข้อมูล integer
        $stmt->execute();

        $affected = $stmt->affected_rows;

        if ($affected <= 0) {
            // หากการอัปเดตล้มเหลวสำหรับ boot ใด ๆ ให้ส่งกลับข้อความแสดงข้อผิดพลาด
            $errorResponse = ["message" => "Failed to update zone with id_date $date_event"];
            $response->getBody()->write(json_encode($errorResponse));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500); // ส่งกลับสถานะ 500 Internal Server Error
        }
    }

    // หากทุกแถวในตาราง zone ถูกอัปเดตสำเร็จ ให้ส่งกลับข้อความแสดงความสำเร็จ
    $data = ["message" => "All zones updated successfully"];
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200); // ส่งกลับสถานะ 200 OK
});





$app->post('/add-event', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // Directory for image uploads
    $imgPath = 'C:/Users/aleny/Desktop/Final/New/my-project/my-project/src/assets/img/event/';

    // Function to handle file uploads
    function handleUpload($fileKey, $imgPath) {
        if (isset($_FILES[$fileKey])) {
            $imgName = uniqid() . '.png'; // Assume PNG file for simplicity
            $targetFilePath = $imgPath . $imgName;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetFilePath)) {
                return $imgName;
            } else {
                return null; // If file move fails
            }
        } else {
            return null; // If no image uploaded
        }
    }
    
    // Handle img_mapzone file upload
    $imgMapzone = handleUpload('img_mapzone', $imgPath);

    // Set status_open_close to an empty string if it's not provided
    $statusOpenClose = isset($_POST['status_open_close']) ? $_POST['status_open_close'] : '';

    // Prepare and execute SQL statement
    $sql = "INSERT INTO date_event (
    name_festival, 
    date_start_reservation, 
    date_end_reservation, 
    date_start_festival, 
    date_end_festival, 
    date_start_payment, 
    date_end_payment, 
    status_open_close, 
    img_mapzone
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $errorResponse = ["message" => "SQL preparation error: " . $conn->error];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Send 500 Internal Server Error status
    }

    // Bind parameters
    $stmt->bind_param(
        'sssssssss', 
        $_POST['name_festival'], 
        $_POST['date_start_reservation'], 
        $_POST['date_end_reservation'], 
        $_POST['date_start_festival'], 
        $_POST['date_end_festival'], 
        $_POST['date_start_payment'], 
        $_POST['date_end_payment'],
        $statusOpenClose,
        $imgMapzone
    );

    $stmt->execute();

    if ($stmt->error) {
        $errorResponse = ["message" => "SQL execution error: " . $stmt->error];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Send 500 Internal Server Error status
    }

    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        $lastIdx = $conn->insert_id;

        // Update all rows in the zone table with the last inserted id
        $updateSql = "UPDATE zone SET id_date=?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('i', $lastIdx);
        $updateStmt->execute();

        if ($updateStmt->error) {
            $errorResponse = ["message" => "Failed to update zone: " . $updateStmt->error];
            $response->getBody()->write(json_encode($errorResponse));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500); // Send 500 Internal Server Error status
        }

        $data = ["message" => "Event created and zone updated successfully", "last_idx" => $lastIdx];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); // Send 200 OK status
    } else {
        // If data insertion fails
        $errorResponse = ["message" => "Failed to create event entry"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Send 500 Internal Server Error status
    }
});


$app->post('/update-event', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // Directory for image uploads
    $imgPath = 'C:/Users/aleny/Desktop/Final/New/my-project/my-project/src/assets/img/event/';

    // Function to handle file uploads
    function handleUpload($fileKey, $imgPath) {
        if (isset($_FILES[$fileKey])) {
            $imgName = uniqid() . '.png'; // Assume PNG file for simplicity
            $targetFilePath = $imgPath . $imgName;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetFilePath)) {
                return $imgName;
            } else {
                return null; // If file move fails
            }
        } else {
            return null; // If no image uploaded
        }
    }
    // Handle img_mapzone file upload
    $imgMapzone = handleUpload('img_mapzone', $imgPath);

    // Set status_open_close to an empty string if it's not provided
    $statusOpenClose = isset($_POST['status_open_close']) ? $_POST['status_open_close'] : '';

    // Prepare and execute SQL statement
    $sql = "UPDATE date_event SET
    name_festival = ?,
    date_start_reservation = ?,
    date_end_reservation = ?, 
    date_start_festival = ?, 
    date_end_festival = ?,
    date_start_payment = ?,
    date_end_payment = ?,
    status_open_close = ?,
    img_mapzone = ?
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    $errorResponse = ["message" => "SQL preparation error: " . $conn->error];
    $response->getBody()->write(json_encode($errorResponse));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500); // Send 500 Internal Server Error status
}

// Bind parameters
$stmt->bind_param(
    'sssssssss', 
    $_POST['name_festival'], 
    $_POST['date_start_reservation'], 
    $_POST['date_end_reservation'], 
    $_POST['date_start_festival'], 
    $_POST['date_end_festival'], 
    $_POST['date_start_payment'], 
    $_POST['date_end_payment'],
    $statusOpenClose,
    $imgMapzone
);

    $stmt->execute();

    if ($stmt->error) {
        $errorResponse = ["message" => "SQL execution error: " . $stmt->error];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Send 500 Internal Server Error status
    }

    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        $lastIdx = $conn->insert_id;
        
        // Update zone table with the last inserted id
        $updateSql = "UPDATE zone SET id_date=?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('i', $lastIdx);
        $updateStmt->execute();

        if ($updateStmt->error) {
            $errorResponse = ["message" => "Failed to update zone: " . $updateStmt->error];
            $response->getBody()->write(json_encode($errorResponse));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500); // Send 500 Internal Server Error status
        }

        $data = ["message" => "Event created and zone updated successfully", "last_idx" => $lastIdx];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); // Send 200 OK status
    } else {
        // If data insertion fails
        $errorResponse = ["message" => "Failed to create event entry"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Send 500 Internal Server Error status
    }
});

?>

