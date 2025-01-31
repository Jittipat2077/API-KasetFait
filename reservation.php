<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
// $app->post('/update-boot-user', function (Request $request, Response $response, $args) {
//     $json = $request->getBody();
//     $jsonData = json_decode($json, true);
//     $conn = $GLOBALS['conn'];

//     foreach ($jsonData as $boot) {
//         $idBoot = $boot['id_boot']; // Extract the boot ID from the data
//         $idReservation = $boot['id_reservation']; // Extract the reservation ID from the data

//         // Proceed with updating the boot
//         $sql = "UPDATE boot SET id_reservation=?, status_boot='เต็ม' WHERE id_boot=?";
//         $stmt = $conn->prepare($sql);
//         $stmt->bind_param('ii', $idReservation, $idBoot); // Assuming 'id_reservation' and 'id_boot' are of type integer
//         $stmt->execute();

//         $affected = $stmt->affected_rows;

//         if ($affected <= 0) {
//             // If the update failed for any reason for a specific boot, return an error response
//             $errorResponse = ["message" => "Failed to update boot with ID $idBoot"];
//             $response->getBody()->write(json_encode($errorResponse));
//             return $response
//                 ->withHeader('Content-Type', 'application/json')
//                 ->withStatus(500); // Return a 500 Internal Server Error status
//         }
//     }

//     // If all boots are updated successfully, return a success response
//     $data = ["message" => "All boots updated successfully"];
//     $response->getBody()->write(json_encode($data));
//     return $response
//         ->withHeader('Content-Type', 'application/json')
//         ->withStatus(200); // Return a 200 OK status
// });

$app->post('/update-reservation', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // Check if img_slip is sent
    if (isset($_FILES['img_slip'])) {
        // $imgPath = 'C:/Users/aleny/Desktop/Final/New/my-project/my-project/src/assets/img/slip/';
        $imgPath = '../assets/img/slip/';
        $imgSlipName = uniqid() . '.png'; 

        // Move uploaded file to target directory
        $targetFilePath = $imgPath . $imgSlipName;
        move_uploaded_file($_FILES['img_slip']['tmp_name'], $targetFilePath);
    }

    // Prepare and execute SQL statement to update reservation
    $sql = "UPDATE reservation SET date_payment=current_timestamp(),  img_slip=?, status=? WHERE id_reservation=?";
    $stmt = $conn->prepare($sql);
    $status = 'อยู่ในระหว่างการตรวจสอบ';
    $stmt->bind_param('sss', $imgSlipName, $status, $_POST['id_reservation']);
    $stmt->execute();

    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        // Update status_boot in boot table
        $updateBootSql = "UPDATE boot SET status_boot='จองแล้ว' WHERE id_reservation=?";
        $updateBootStmt = $conn->prepare($updateBootSql);
        $updateBootStmt->bind_param('s', $_POST['id_reservation']);
        $updateBootStmt->execute();

        // Return success response
        $data = ["affected_rows" => $affected];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); 
    } else {
        // Return error response if update failed
        $errorResponse = ["message" => "Failed to update reservation"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); 
    }
});

$app->delete('/delete-reservations', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // เริ่มธุรกรรม
    $conn->begin_transaction();

    try {
        $sqlUpdate = "UPDATE boot SET id_reservation = NULL, status_boot = 'ว่าง' WHERE status_boot = 'อยู่ระหว่างตรวจสอบ'";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->execute();

        $affectedUpdate = $stmtUpdate->affected_rows;

        if ($affectedUpdate < 0) {
            throw new Exception("Failed to update any boots.");
        }

        // ลบการจองที่มีสถานะเป็น 'อยู่ในระหว่างการตรวจสอบ'
        $sqlDelete = "DELETE FROM reservation WHERE status = 'ยังไม่ชำระเงิน'";
        $stmtDelete = $conn->prepare($sqlDelete);
        $stmtDelete->execute();

        $affectedDelete = $stmtDelete->affected_rows;

        if ($affectedDelete <= 0) {
            throw new Exception("Failed to delete any reservations.");
        }

        // ยืนยันธุรกรรม
        $conn->commit();

        $data = ["message" => "Boots updated and reservations with status 'อยู่ในระหว่างการตรวจสอบ' deleted successfully"];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

    } catch (Exception $e) {
        // ยกเลิกธุรกรรมในกรณีเกิดข้อผิดพลาด
        $conn->rollback();

        $errorResponse = ["message" => $e->getMessage()];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});


$app->delete('/delete-reservation/{id_reservation}', function (Request $request, Response $response, $args) {
    $idReservation = $args['id_reservation'];
    $conn = $GLOBALS['conn'];

    // Update the boot
    $sqlUpdate = "UPDATE boot SET id_reservation=NULL, status_boot='ว่าง' WHERE id_reservation=?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bind_param('i', $idReservation);
    $stmtUpdate->execute();

    $affectedUpdate = $stmtUpdate->affected_rows;

    if ($affectedUpdate <= 0) {
        $errorResponse = ["message" => "Failed to update boot with reservation ID $idReservation"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }

    // Delete the reservation
    $sqlDelete = "DELETE FROM reservation WHERE id_reservation=?";
    $stmtDelete = $conn->prepare($sqlDelete);
    $stmtDelete->bind_param('i', $idReservation);
    $stmtDelete->execute();

    $affectedDelete = $stmtDelete->affected_rows;

    if ($affectedDelete <= 0) {
        $errorResponse = ["message" => "Failed to delete reservation with ID $idReservation"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }

    $data = ["message" => "Boot updated and reservation deleted successfully"];
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});


$app->post('/add-reservation', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // Retrieve the POST parameters
    $data = $request->getParsedBody();
    $paymentStatus = $data['payment_status'] ?? null; // Get payment status from request, if exists

    // Check if img_slip was sent
    if (isset($_FILES['img_slip'])) {
        $imgPath = '../assets/img/slip/';
        $imgSlipName = uniqid() . '.png'; // Assume it's a PNG file for simplicity

        // Move the uploaded file to the target directory
        $targetFilePath = $imgPath . $imgSlipName;
        move_uploaded_file($_FILES['img_slip']['tmp_name'], $targetFilePath);

        $status = "อยู่ในระหว่างการตรวจสอบ"; // Set status to "under review"
        $datePayment = date('Y-m-d H:i:s'); // Set payment date to current time
    } else {
        $imgSlipName = null; // No image uploaded
        
        // Check if the payment status is cash payment
        if ($paymentStatus === 'cash_payment') {
            $status = "อยู่ในระหว่างการตรวจสอบชําระเงินสด"; // Set status to "cash payment"
            $datePayment = null; // Set payment date to null
        } else {
            $status = "ยังไม่ชำระเงิน"; // Set status to "not paid"
            $datePayment = null; // Payment date is null
        }
    }

    // Prepare the values for each sell column with the prefixes
    $sell = "ลําดับที่ 1 " . ($data['sell'] ?? ""); // Value for sell with prefix
    $sell_two = "ลําดับที่ 2 " . ($data['sell_two'] ?? ""); // Value for sell_two with prefix
    $sell_three = "ลําดับที่ 3 " . ($data['sell_three'] ?? ""); // Value for sell_three with prefix
    $sell_four = "ลําดับที่ 4 " . ($data['sell_four'] ?? ""); // Value for sell_four with prefix

    // Prepare and execute the SQL statement
    $sql = "INSERT INTO reservation (id, date_payment, sell, sell_two, sell_three, sell_four, img_slip, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        $errorResponse = ["message" => "Failed to prepare SQL statement", "error" => $conn->error];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Send back 500 Internal Server Error
    }

    // Binding parameters
    $stmt->bind_param('ssssssss', $data['id'], $datePayment, $sell, $sell_two, $sell_three, $sell_four, $imgSlipName, $status);
    $stmt->execute();

    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        $data = ["affected_rows" => $affected, "last_idx" => $conn->insert_id];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); // Send back 200 OK
    } else {
        // If insertion failed
        $errorResponse = ["message" => "Failed to create reservation"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Send back 500 Internal Server Error
    }
});



// $app->post('/add-reservation', function (Request $request, Response $response, $args) {
//     $conn = $GLOBALS['conn'];

//     // Check if the email already exists
//     // Proceed with inserting the new user

//     // Check if img_slip is provided
//     if (isset($_FILES['img_slip'])) {
//         $imgSlipPath = 'C:/Users/aleny/Desktop/Final/my-project/my-project/src/assets/img/slip/';

//         $imgSlipName = uniqid() . '.png'; // Assuming PNG format for simplicity, you may adjust accordingly

//         // Move the uploaded file to the target directory
//         $targetFilePath = $imgSlipPath . $imgSlipName;
//         move_uploaded_file($_FILES['img_slip']['tmp_name'], $targetFilePath);

//         $status = "อยู่ในระหว่างการตรวจสอบ"; // Set status to "อยู่ในระหว่างการตรวจสอบ"
//     } else {
//         $status = "ยังไม่ชำระเงิน"; // Set status to "ยังไม่ชำระเงิน"
//     }

//     // Prepare and execute the SQL statement
//     $sql = "INSERT INTO reservation (id, date_reserva, date_payment, sell, img_slip, status) VALUES (?, current_timestamp(), current_timestamp(), ?, ?, ?)";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('ssss', $_POST['id'], $_POST['sell'], $targetFilePath, $status);
//     $stmt->execute();

//     $affected = $stmt->affected_rows;

//     if ($affected > 0) {
//         $data = ["affected_rows" => $affected, "last_idx" => $conn->insert_id];
//         $response->getBody()->write(json_encode($data));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(200); // Return a 200 OK status
//     } else {
//         // If the insert failed
//         $errorResponse = ["message" => "Failed to create reservation"];
//         $response->getBody()->write(json_encode($errorResponse));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(500); // Return a 500 Internal Server Error status
//     }
// });

// $app->post('/updateboot-reservation/{id_boot}', function (Request $request, Response $response, array $args) {
//     $conn = $GLOBALS['conn'];
//     $id_boot = $args['id_boot'];
//     $id_reservation = $request->getQueryParams()['id_reservation'];
//  // Get id_reservation from request parameters

//     // Prepare and execute the SQL statement to update id_reservation for the specified boot
//     $sql = "UPDATE boot SET id_reservation = ? WHERE id_boot = ?";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('ii', $id_reservation, $id_boot);
//     $stmt->execute();

//     $affected = $stmt->affected_rows;

//     if ($affected > 0) {
//         $data = ["message" => "ID reservation updated successfully"];
//         $response->getBody()->write(json_encode($data));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(200); // Return a 200 OK status
//     } else {
//         // If the update failed
//         $errorResponse = ["message" => "Failed to update ID reservation"];
//         $response->getBody()->write(json_encode($errorResponse));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(500); // Return a 500 Internal Server Error status
//     }
// });
// $app->get('/reservation/{id_reservation}', function (Request $request, Response $response, $args) {
//     $conn = $GLOBALS['conn'];
//     $id_reservation = $args['id_reservation'];
//     $sql = 'SELECT * FROM reservation WHERE id_reservation = ?';
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('i', $id_reservation); // Assuming id_reservation is an integer
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     if ($result->num_rows > 0) {
//         $data = $result->fetch_assoc();
//         $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
//         return $response
//             ->withHeader('Content-Type', 'application/json; charset=utf-8')
//             ->withStatus(200);
//     } else {
//         $response->getBody()->write(json_encode(["message" => "Reservation not found"], JSON_UNESCAPED_UNICODE));
//         return $response
//             ->withHeader('Content-Type', 'application/json; charset=utf-8')
//             ->withStatus(404);
//     }
// });
// $app->get('/boot-reservationprice/{id_boot}', function (Request $request, Response $response, array $args) {
//     $conn = $GLOBALS['conn'];
//     $id_boot = explode(',', $args['id_boot']); // Separate id_boot received with ','

//     $placeholders = rtrim(str_repeat('?,', count($id_boot)), ','); // Create placeholders for binding parameters
//     $types = str_repeat('i', count($id_boot)); // Set parameter types to integer

//     $sql = 'SELECT  SUM(boot.price) AS total_price, zone.id_zone 
//         FROM boot
//         INNER JOIN zone ON boot.id_zone = zone.id_zone
//         WHERE boot.id_boot IN (' . $placeholders . ')'; // Use IN clause to select data from multiple id_boot
//     $stmt = $conn->prepare($sql);

//     // Use call_user_func_array to bind parameters dynamically
//     $params = array_merge(array($types), $id_boot);
//     $refParams = array();
//     foreach($params as $key => $value) {
//         $refParams[$key] = &$params[$key]; // Create references to parameters
//     }
//     call_user_func_array(array($stmt, 'bind_param'), $refParams); // Pass parameters by reference

//     $stmt->execute();
//     $result = $stmt->get_result();

//     $data = array();
//     foreach ($result as $row) {
//         array_push($data, $row);
//     }

//     $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));

//     return $response
//         ->withHeader('Content-Type', 'application/json; charset=utf-8')
//         ->withStatus(200);
// });
$app->get('/boot-reservation/{id_boot}', function (Request $request, Response $response, array $args) {
    $conn = $GLOBALS['conn'];
    $id_boot = explode(',', $args['id_boot']); // Separate id_boot received with ','

    $placeholders = rtrim(str_repeat('?,', count($id_boot)), ','); // Create placeholders for binding parameters
    $types = str_repeat('i', count($id_boot)); // Set parameter types to integer

    $sql = 'SELECT boot.*, zone.id_zone 
        FROM boot
        INNER JOIN zone ON boot.id_zone = zone.id_zone
        WHERE boot.id_boot IN (' . $placeholders . ')'; // Use IN clause to select data from multiple id_boot
    $stmt = $conn->prepare($sql);

    // Use call_user_func_array to bind parameters dynamically
    $params = array_merge(array($types), $id_boot);
    $refParams = array();
    foreach($params as $key => $value) {
        $refParams[$key] = &$params[$key]; // Create references to parameters
    }
    call_user_func_array(array($stmt, 'bind_param'), $refParams); // Pass parameters by reference

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

$app->get('/boot-reservation-new/{id_boot}', function (Request $request, Response $response, array $args) {
    $conn = $GLOBALS['conn'];
    $id_boot = explode(',', $args['id_boot']); // Separate id_boot received with ','

    $placeholders = rtrim(str_repeat('?,', count($id_boot)), ','); // Create placeholders for binding parameters
    $types = str_repeat('i', count($id_boot)); // Set parameter types to integer

    $sql = 'SELECT boot.*, zone.id_zone 
        FROM boot
        INNER JOIN zone ON boot.id_zone = zone.id_zone
        WHERE boot.id_boot IN (' . $placeholders . ') AND boot.status_boot = "ว่าง"'; // Use IN clause to select data from multiple id_boot and check status_boot
    $stmt = $conn->prepare($sql);

    // Use call_user_func_array to bind parameters dynamically
    $params = array_merge(array($types), $id_boot);
    $refParams = array();
    foreach($params as $key => $value) {
        $refParams[$key] = &$params[$key]; // Create references to parameters
    }
    call_user_func_array(array($stmt, 'bind_param'), $refParams); // Pass parameters by reference

    $stmt->execute();
    $result = $stmt->get_result();

    $totalPrice = 0; // Initialize total price

    $data = array();
    foreach ($result as $row) {
        $totalPrice += $row['price']; // Add each boot's price to total price
        array_push($data, $row);
    }
    $responseData = array(
        'totalPrice' => $totalPrice,
        'boots' => $data
    );

    $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});




// ----------------------testjson----------------------------------
// $app->post('/add-reservation', function (Request $request, Response $response, $args) {
//     $json = $request->getBody();
//     $jsonData = json_decode($json, true);
//     $conn = $GLOBALS['conn'];

//     // Check if the email already exists
//     // Proceed with inserting the new user

//     // Check if img_slip is provided
//     if (isset($jsonData['img_slip'])) {
//         // Handle file upload
//         $imgSlipPath = 'C:\xampp\htdocs\upload'; // Specify the directory to store uploaded files
//         $imgSlipName = uniqid() . '-' . basename($jsonData['img_slip']['name']);
//         $targetFilePath = $imgSlipPath . $imgSlipName;
//         move_uploaded_file($jsonData['img_slip']['tmp_name'], $targetFilePath);

//         $sql = "INSERT INTO reservation(id, date_reserva, date_payment, sell, img_slip, status) VALUES (?, current_timestamp(), current_timestamp(), ?, ?, ?)";
//         $stmt = $conn->prepare($sql);
//         $status = "อยู่ในระหว่างการตรวจสอบ"; // Set status to "อยู่ในระหว่างการตรวจสอบ" if img_slip is provided
//         $stmt->bind_param('ssss', $jsonData['id'], $jsonData['sell'], $targetFilePath, $status);
//     } else {
//         $sql = "INSERT INTO reservation(id, date_reserva, sell, status) VALUES (?, current_timestamp(), ?, ?)";
//         $stmt = $conn->prepare($sql);
//         $status = "ยังไม่ชำระเงิน"; // Set status to "ยังไม่ชำระเงิน" if img_slip is not provided
//         $stmt->bind_param('sss', $jsonData['id'], $jsonData['sell'], $status);
//     }
    
//     $stmt->execute();

//     $affected = $stmt->affected_rows;

//     if ($affected > 0) {
//         $data = ["affected_rows" => $affected, "last_idx" => $conn->insert_id];
//         $response->getBody()->write(json_encode($data));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(200); // Return a 200 OK status
//     } else {
//         // If the insert failed for any reason other than duplicate email
//         $errorResponse = ["message" => "Failed to create user"];
//         $response->getBody()->write(json_encode($errorResponse));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(500); // Return a 500 Internal Server Error status
//     }
// });

// ----------------------test api----------------------------------
// $app->post('/add-reservation', function (Request $request, Response $response, $args) {
//     $conn = $GLOBALS['conn'];

//     // Retrieve JSON data
//     $jsonData = json_decode($request->getBody(), true);

//     // Check if the email already exists
//     // Proceed with inserting the new user

//     // Check if img_slip is provided
//  // Check if img_slip is provided
// if (isset($jsonData['img_slip'])) {
//     // Decode Base64 image data
//     $imgData = base64_decode($jsonData['img_slip']);

//     // Generate a unique file name
//     $imgSlipPath = 'C:/Users/aleny/Desktop/Final/my-project/my-project/src/assets/img/slip/';

//     $imgSlipName = uniqid() . '.png'; // Assuming PNG format for simplicity, you may adjust accordingly

//     // Write the decoded image data to the file
//     $targetFilePath = $imgSlipPath . $imgSlipName;
//     $file = fopen($targetFilePath, 'wb');
//     fwrite($file, $imgData);
//     fclose($file);

//     $status = "อยู่ในระหว่างการตรวจสอบ"; // Set status to "อยู่ในระหว่างการตรวจสอบ"
// } else {
//     $status = "ยังไม่ชำระเงิน"; // Set status to "ยังไม่ชำระเงิน"
// }


//     // Prepare and execute the SQL statement
//     $sql = "INSERT INTO reservation (id, date_reserva, date_payment, sell, img_slip, status) VALUES (?, current_timestamp(), current_timestamp(), ?, ?, ?)";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('ssss', $jsonData['id'], $jsonData['sell'], $targetFilePath, $status);
//     $stmt->execute();

//     $affected = $stmt->affected_rows;

//     if ($affected > 0) {
//         $data = ["affected_rows" => $affected, "last_idx" => $conn->insert_id];
//         $response->getBody()->write(json_encode($data));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(200); // Return a 200 OK status
//     } else {
//         // If the insert failed
//         $errorResponse = ["message" => "Failed to create reservation"];
//         $response->getBody()->write(json_encode($errorResponse));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(500); // Return a 500 Internal Server Error status
//     }
// });






// // -----------------------เรียกข้อมูลจาก บูธ จาก id_reservation (ไอดีการจอง)-------------------
// $app->get('/reservation/{id_reservation}', function (Request $request, Response $response, array $args) {
//     $conn = $GLOBALS['conn'];
//     $id_reservation = $args['id_reservation'];
    
//     $sql = 'SELECT boot.*, reservation.id_reservation 
//         FROM boot
//         INNER JOIN reservation ON boot.id_reservation = reservation.id_reservation
//         WHERE reservation.id_reservation = ?
//         ORDER BY CAST(boot.number_boot AS UNSIGNED) ASC'; // เรียงลำดับตัวเลขจากน้อยไปมาก
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("i", $id_reservation);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     $data = array();
//     foreach ($result as $row) {
//         array_push($data, $row);
//     }
    
//     $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    
//     return $response
//         ->withHeader('Content-Type', 'application/json; charset=utf-8')
//         ->withStatus(200);
// });



// $app->get('/reservation/{id_account}', function (Request $request, Response $response, array $args) {
//     $conn = $GLOBALS['conn'];
//     $id_account = $args['id_account'];
    
//     $sql = 'SELECT boot.*, reservation.id_reservation AS reservation_id, account.*, reservation.* 
//         FROM boot
//         INNER JOIN reservation ON boot.id_reservation = reservation.id_reservation
//         INNER JOIN account ON reservation.id = account.id
//         WHERE account.id = ?';
        
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("i", $id_account);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     $data = array();
//     foreach ($result as $row) {
//         array_push($data, $row);
//     }
    
//     $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    
//     return $response
//         ->withHeader('Content-Type', 'application/json; charset=utf-8')
//         ->withStatus(200);
// }); 

// $app->get('/reservation/{id_reservation}', function (Request $request, Response $response, array $args) {
//     $conn = $GLOBALS['conn'];
//     $id_reservation = $args['id_reservation'];
    
//     $sql = 'SELECT boot.*, reservation.id_reservation AS reservation_id, account.*, reservation.* 
//         FROM boot
//         INNER JOIN reservation ON boot.id_reservation = reservation.id_reservation
//         INNER JOIN account ON reservation.id = account.id
//         WHERE boot.id_reservation = ?';
        
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("i", $id_reservation);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     $data = array();
//     foreach ($result as $row) {
//         array_push($data, $row);
//     }
    
//     $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    
//     return $response
//         ->withHeader('Content-Type', 'application/json; charset=utf-8')
//         ->withStatus(200);
// }); 
// -----------------------เรียกข้อมูลการจองจากไอดี -------------------

// $app->get('/reservation/{id_reservation}', function (Request $request, Response $response, $args) {
//     $conn = $GLOBALS['conn'];
//     $id_reservation = $args['id_reservation'];
//     $sql = 'SELECT * FROM reservation WHERE id_reservation = ?';
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('i', $id_reservation); // Assuming id_reservation is an integer
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     if ($result->num_rows > 0) {
//         $data = $result->fetch_assoc();
//         $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
//         return $response
//             ->withHeader('Content-Type', 'application/json; charset=utf-8')
//             ->withStatus(200);
//     } else {
//         $response->getBody()->write(json_encode(["message" => "Reservation not found"], JSON_UNESCAPED_UNICODE));
//         return $response
//             ->withHeader('Content-Type', 'application/json; charset=utf-8')
//             ->withStatus(404);
//     }
// });

$app->get('/apply-reservation-approve-cash/{id_reservation}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // Get id_reservation from the URL parameters
    $id_reservation = $args['id_reservation'] ?? null;

    if ($id_reservation === null) {
        $errorResponse = ["message" => "id_reservation is required"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400); // Return a 400 Bad Request status
    }

    // Fetch the current data based on id_reservation
    $sql = 'SELECT reservation.*, boot.*, account.* FROM reservation 
            JOIN boot ON reservation.id_reservation = boot.id_reservation
            JOIN account ON reservation.id = account.id
            WHERE reservation.id_reservation = ?'; // Filter by id_reservation

    // Prepare the SQL statement
    $stmt = $conn->prepare($sql);

    // Bind the id_reservation parameter to the SQL statement
    $stmt->bind_param('s', $id_reservation);

    // Execute the SQL statement
    $stmt->execute();

    // Get the result set from the executed statement
    $result = $stmt->get_result();

    // Initialize an array to hold the fetched data
    $data = array();

    // Fetch each row as an associative array and add it to the data array
    while ($row = $result->fetch_assoc()) {
        array_push($data, $row);
    }

    // Check if any data was found
    if (empty($data)) {
        $errorResponse = ["message" => "No reservation found with the provided id_reservation"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404); // Return a 404 Not Found status
    }

    // Update the status and date_payment in the reservation table
    $updateReservationSql = 'UPDATE reservation SET status = ?, date_payment = NOW() WHERE id_reservation = ?';
    $updateReservationStmt = $conn->prepare($updateReservationSql);
    $newReservationStatus = 'ชําระเงินสดเเล้ว'; // new status for reservation
    $updateReservationStmt->bind_param('ss', $newReservationStatus, $id_reservation);
    $updateReservationStmt->execute();

    // Update the status_boot in the boot table
    $updateBootSql = 'UPDATE boot SET status_boot = ? WHERE id_reservation = ?';
    $updateBootStmt = $conn->prepare($updateBootSql);
    $newBootStatus = 'จองเเล้ว'; // new status for boot
    $updateBootStmt->bind_param('ss', $newBootStatus, $id_reservation);
    $updateBootStmt->execute();

    // Fetch the updated data to return in the response
    $stmt->execute();
    $result = $stmt->get_result();
    $updatedData = array();
    while ($row = $result->fetch_assoc()) {
        array_push($updatedData, $row);
    }

    // Encode the updated data array to JSON and write it to the response body
    $response->getBody()->write(json_encode($updatedData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));

    // Set the Content-Type header and return the response with a 200 OK status
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});


$app->get('/apply-reservation-approve/{id_reservation}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // Get id_reservation from the URL parameters
    $id_reservation = $args['id_reservation'] ?? null;

    if ($id_reservation === null) {
        $errorResponse = ["message" => "id_reservation is required"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400); // Return a 400 Bad Request status
    }

    // Fetch the current data based on id_reservation
    $sql = 'SELECT reservation.*, boot.*, account.* FROM reservation 
            JOIN boot ON reservation.id_reservation = boot.id_reservation
            JOIN account ON reservation.id = account.id
            WHERE reservation.id_reservation = ?'; // Filter by id_reservation

    // Prepare the SQL statement
    $stmt = $conn->prepare($sql);

    // Bind the id_reservation parameter to the SQL statement
    $stmt->bind_param('s', $id_reservation);

    // Execute the SQL statement
    $stmt->execute();

    // Get the result set from the executed statement
    $result = $stmt->get_result();

    // Initialize an array to hold the fetched data
    $data = array();

    // Fetch each row as an associative array and add it to the data array
    while ($row = $result->fetch_assoc()) {
        array_push($data, $row);
    }

    // Check if any data was found
    if (empty($data)) {
        $errorResponse = ["message" => "No reservation found with the provided id_reservation"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404); // Return a 404 Not Found status
    }

    // Update the status in the reservation table
    $updateReservationSql = 'UPDATE reservation SET status = ? WHERE id_reservation = ?';
    $updateReservationStmt = $conn->prepare($updateReservationSql);
    $newReservationStatus = 'จองสําเร็จ'; // new status for reservation
    $updateReservationStmt->bind_param('ss', $newReservationStatus, $id_reservation);
    $updateReservationStmt->execute();

    // Update the status_boot in the boot table
    $updateBootSql = 'UPDATE boot SET status_boot = ? WHERE id_reservation = ?';
    $updateBootStmt = $conn->prepare($updateBootSql);
    $newBootStatus = 'จองเเล้ว'; // new status for boot
    $updateBootStmt->bind_param('ss', $newBootStatus, $id_reservation);
    $updateBootStmt->execute();

    // Fetch the updated data to return in the response
    $stmt->execute();
    $result = $stmt->get_result();
    $updatedData = array();
    while ($row = $result->fetch_assoc()) {
        array_push($updatedData, $row);
    }

    // Encode the updated data array to JSON and write it to the response body
    $response->getBody()->write(json_encode($updatedData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));

    // Set the Content-Type header and return the response with a 200 OK status
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});


$app->get('/apply-reservation-payagain/{id_reservation}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // Get id_reservation from the URL parameters
    $id_reservation = $args['id_reservation'] ?? null;

    if ($id_reservation === null) {
        $errorResponse = ["message" => "id_reservation is required"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400); // Return a 400 Bad Request status
    }

    // Fetch the current data based on id_reservation
    $sql = 'SELECT reservation.*, boot.*, account.* FROM reservation 
            JOIN boot ON reservation.id_reservation = boot.id_reservation
            JOIN account ON reservation.id = account.id
            WHERE reservation.id_reservation = ?'; // Filter by id_reservation

    // Prepare the SQL statement
    $stmt = $conn->prepare($sql);

    // Bind the id_reservation parameter to the SQL statement
    $stmt->bind_param('s', $id_reservation);

    // Execute the SQL statement
    $stmt->execute();

    // Get the result set from the executed statement
    $result = $stmt->get_result();

    // Initialize an array to hold the fetched data
    $data = array();

    // Fetch each row as an associative array and add it to the data array
    while ($row = $result->fetch_assoc()) {
        array_push($data, $row);
    }

    // Check if any data was found
    if (empty($data)) {
        $errorResponse = ["message" => "No reservation found with the provided id_reservation"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404); // Return a 404 Not Found status
    }

    // Update the status in the reservation table
    $updateReservationSql = 'UPDATE reservation SET status = ? WHERE id_reservation = ?';
    $updateReservationStmt = $conn->prepare($updateReservationSql);
    $newReservationStatus = 'กรุณาชําระเงินอีกครั้ง'; // new status for reservation
    $updateReservationStmt->bind_param('ss', $newReservationStatus, $id_reservation);
    $updateReservationStmt->execute();

    // Update the status_boot in the boot table
    $updateBootSql = 'UPDATE boot SET status_boot = ? WHERE id_reservation = ?';
    $updateBootStmt = $conn->prepare($updateBootSql);
    $newBootStatus = 'อยู่ระหว่างตรวจสอบ'; // new status for boot
    $updateBootStmt->bind_param('ss', $newBootStatus, $id_reservation);
    $updateBootStmt->execute();

    // Fetch the updated data to return in the response
    $stmt->execute();
    $result = $stmt->get_result();
    $updatedData = array();
    while ($row = $result->fetch_assoc()) {
        array_push($updatedData, $row);
    }

    // Encode the updated data array to JSON and write it to the response body
    $response->getBody()->write(json_encode($updatedData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));

    // Set the Content-Type header and return the response with a 200 OK status
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});


// -----------------------เรียกข้อมูลการจองจากไออดีการจอง -------------------
$app->get('/reservation-approve/{id_reservation}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];
    $id_reservation = $args['id_reservation']; // Get the id_reservation from the URL
    // Update the SQL query to include additional sell fields
    $sql = 'SELECT reservation.*, boot.*, account.*, zone.*, 
                   boot.img_boot, boot.boot_size, 
                   reservation.sell_two, reservation.sell_three, reservation.sell_four 
            FROM reservation 
            JOIN boot ON reservation.id_reservation = boot.id_reservation
            JOIN account ON reservation.id = account.id
            JOIN zone ON boot.id_zone = zone.id_zone
            WHERE reservation.id_reservation = ? AND reservation.status = ?';

    $stmt = $conn->prepare($sql);

    // Check if the SQL statement was prepared successfully
    if (!$stmt) {
        $errorResponse = ["message" => "Failed to prepare SQL statement", "error" => $conn->error];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }

    $status = 'อยู่ในระหว่างการตรวจสอบ';
    $stmt->bind_param('ss', $id_reservation, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();

    while ($row = $result->fetch_assoc()) {
        $reservationId = $row['id_reservation'];
        $accountId = $row['id'];

        // If this reservation is not yet in the array, add it
        if (!isset($data[$reservationId])) {
            $data[$reservationId] = array(
                'reservation' => array(
                    'id_reservation' => $row['id_reservation'],
                    'date_reserva' => $row['date_reserva'],
                    'date_payment' => $row['date_payment'],
                    'sell' => $row['sell'],
                    'sell_two' => $row['sell_two'], // Include sell_two
                    'sell_three' => $row['sell_three'], // Include sell_three
                    'sell_four' => $row['sell_four'], // Include sell_four
                    'img_slip' => $row['img_slip'],
                    'status' => $row['status']
                ),
                'account' => array()
            );
        }

        // If this account is not yet in the reservation, add it
        if (!isset($data[$reservationId]['account'][$accountId])) {
            $data[$reservationId]['account'][$accountId] = array(
                'id' => $row['id'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'boot' => array() // Initialize boots array
            );
        }

        // Add the related boot information along with zone data
        $data[$reservationId]['account'][$accountId]['boot'][] = array(
            'id_zone' => $row['id_zone'],
            'number_boot' => $row['number_boot'],
            'price' => $row['price'],
            'img_boot' => $row['img_boot'],
            'boot_size' => $row['boot_size'],
            'zone' => array(
                'id_zone' => $row['id_zone'],
                'name_zone' => $row['name_zone'],
            )
        );
    }

    // Change keys to be an array of objects
    $responseData = [];
    foreach ($data as $reservation) {
        $reservation['account'] = array_values($reservation['account']);
        $responseData[] = $reservation;
    }

    $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});


$app->get('/check-reservation-approve/{id_reservation}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];
    $id_reservation = $args['id_reservation']; // Get the id_reservation from the URL

    // Update the SQL query to include additional sell fields
    $sql = 'SELECT reservation.*, boot.*, account.*, zone.*, 
                   boot.img_boot, boot.boot_size, 
                   reservation.sell_two, reservation.sell_three, reservation.sell_four 
            FROM reservation 
            JOIN boot ON reservation.id_reservation = boot.id_reservation
            JOIN account ON reservation.id = account.id
            JOIN zone ON boot.id_zone = zone.id_zone
            WHERE reservation.id_reservation = ? 
            AND (reservation.status = ? OR reservation.status = ?)'; // รองรับสองสถานะ

    $stmt = $conn->prepare($sql);

    // Check if the SQL statement was prepared successfully
    if (!$stmt) {
        $errorResponse = ["message" => "Failed to prepare SQL statement", "error" => $conn->error];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }

    // เพิ่มตัวแปรสำหรับสถานะทั้งสอง
    $statusPaid = 'จองสําเร็จ';
    $statusCash = 'ชําระเงินสดเเล้ว';
    
    // Bind parameters and execute the statement
    $stmt->bind_param('sss', $id_reservation, $statusPaid, $statusCash);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();

    while ($row = $result->fetch_assoc()) {
        $reservationId = $row['id_reservation'];
        $accountId = $row['id'];

        // If this reservation is not yet in the array, add it
        if (!isset($data[$reservationId])) {
            $data[$reservationId] = array(
                'reservation' => array(
                    'id_reservation' => $row['id_reservation'],
                    'date_reserva' => $row['date_reserva'],
                    'date_payment' => $row['date_payment'],
                    'sell' => $row['sell'],
                    'sell_two' => $row['sell_two'], // Include sell_two
                    'sell_three' => $row['sell_three'], // Include sell_three
                    'sell_four' => $row['sell_four'], // Include sell_four
                    'img_slip' => $row['img_slip'],
                    'status' => $row['status'] // ดึงสถานะมาแสดง
                ),
                'account' => array()
            );
        }

        // If this account is not yet in the reservation, add it
        if (!isset($data[$reservationId]['account'][$accountId])) {
            $data[$reservationId]['account'][$accountId] = array(
                'id' => $row['id'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'boot' => array() // Initialize boots array
            );
        }

        // Add the related boot information along with zone data
        $data[$reservationId]['account'][$accountId]['boot'][] = array(
            'id_zone' => $row['id_zone'],
            'number_boot' => $row['number_boot'],
            'price' => $row['price'],
            'img_boot' => $row['img_boot'],
            'boot_size' => $row['boot_size'],
            'zone' => array(
                'id_zone' => $row['id_zone'],
                'name_zone' => $row['name_zone'],
            )
        );
    }

    // Change keys to be an array of objects
    $responseData = [];
    foreach ($data as $reservation) {
        $reservation['account'] = array_values($reservation['account']);
        $responseData[] = $reservation;
    }

    $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});


// -----------------------เรียกข้อมูลการจองทั้งหมด -------------------

$app->get('/reservation-wait', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];

    // ตั้งค่า locale ให้เป็นภาษาไทย
    $conn->query("SET lc_time_names = 'th_TH'");

    // Update the SQL query to format dates and retrieve additional fields
    $sql = 'SELECT 
                reservation.id_reservation,
                DATE_FORMAT(DATE_ADD(reservation.date_reserva, INTERVAL 543 YEAR), "%e %M %Y") AS date_reserva,
                DATE_FORMAT(DATE_ADD(reservation.date_payment, INTERVAL 543 YEAR), "%e %M %Y") AS date_payment,
                reservation.sell,
                reservation.img_slip,
                reservation.status,
                boot.id_boot,
                boot.number_boot,
                boot.price,
                account.id,
                account.firstname,
                account.lastname,
                account.email,
                zone.id_zone,
                zone.name_zone
            FROM 
                reservation 
            JOIN 
                boot ON reservation.id_reservation = boot.id_reservation
            JOIN 
                account ON reservation.id = account.id
            JOIN 
                zone ON boot.id_zone = zone.id_zone
            WHERE 
                reservation.status = ?';

    $stmt = $conn->prepare($sql);

    // ตรวจสอบว่าคำสั่ง SQL ถูกเตรียมสำเร็จหรือไม่
    if (!$stmt) {
        $errorResponse = ["message" => "Failed to prepare SQL statement", "error" => $conn->error];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }

    $status = 'อยู่ในระหว่างการตรวจสอบ';
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();

    while ($row = $result->fetch_assoc()) {
        $reservationId = $row['id_reservation'];
        $accountId = $row['id'];
        
        // ถ้าไม่มีข้อมูล reservation นี้ใน array ให้เพิ่ม
        if (!isset($data[$reservationId])) {
            $data[$reservationId] = array(
                'reservation' => array(
                    'id_reservation' => $row['id_reservation'],
                    'date_reserva' => $row['date_reserva'],
                    'date_payment' => $row['date_payment'],
                    'sell' => $row['sell'],
                    'img_slip' => $row['img_slip'],
                    'status' => $row['status']
                ),
                'account' => array()
            );
        }

        // ถ้าไม่มีข้อมูล account นี้ใน reservation นี้ให้เพิ่ม
        if (!isset($data[$reservationId]['account'][$accountId])) {
            $data[$reservationId]['account'][$accountId] = array(
                'id' => $row['id'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'boot' => array() // Initialize boots array
            );
        }

        // เพิ่มข้อมูล boot ที่เกี่ยวข้องพร้อมข้อมูล zone
        $data[$reservationId]['account'][$accountId]['boot'][] = array(
            'id_zone' => $row['id_zone'],
            'number_boot' => $row['number_boot'],
            'price' => $row['price'],
            'zone' => array(
                'id_zone' => $row['id_zone'],
                'name_zone' => $row['name_zone'],
            )
        );
    }

    // เปลี่ยนค่า key ให้เป็น array ของ object
    $responseData = [];
    foreach ($data as $reservation) {
        $reservation['account'] = array_values($reservation['account']);
        $responseData[] = $reservation;
    }

    $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});


// $app->get('/list-reservation-approve', function (Request $request, Response $response) {
//     $conn = $GLOBALS['conn'];
//     $sql = 'SELECT reservation.*, boot.*, account.* FROM reservation 
//             JOIN boot ON reservation.id_reservation = boot.id_reservation
//             JOIN account ON reservation.id = account.id
//             WHERE reservation.status = ?'; // Add WHERE clause to filter by status

//     $stmt = $conn->prepare($sql);
//     $status = 'ชําระเงินเเล้ว'; // Define the status to filter by
//     $stmt->bind_param('s', $status); // Bind the status parameter
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $data = array();
//     while ($row = $result->fetch_assoc()) { // Use fetch_assoc() to fetch rows
//         array_push($data, $row);
//     }

//     $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
//     return $response
//         ->withHeader('Content-Type', 'application/json; charset=utf-8')
//         ->withStatus(200);
// });
$app->get('/list-reservation-approve', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];
    $conn->query("SET lc_time_names = 'th_TH'");
    $status1 = 'ชําระเงินสดเเล้ว';
    $status2 = 'จองสําเร็จ';
    $sql = 'SELECT 
                reservation.id_reservation,
                DATE_FORMAT(DATE_ADD(reservation.date_reserva, INTERVAL 543 YEAR), "%e %M %Y") AS date_reserva,
                DATE_FORMAT(DATE_ADD(reservation.date_payment, INTERVAL 543 YEAR), "%e %M %Y") AS date_payment,
                reservation.sell,
                reservation.sell_two,
                reservation.sell_three,
                reservation.sell_four,
                reservation.img_slip,
                reservation.status,
                boot.id_boot,
                boot.number_boot,
                boot.price,
                account.id,
                account.firstname,
                account.lastname,
                account.email,
                zone.id_zone,
                zone.name_zone
            FROM 
                reservation 
            JOIN 
                boot ON reservation.id_reservation = boot.id_reservation
            JOIN 
                account ON reservation.id = account.id
            JOIN 
                zone ON boot.id_zone = zone.id_zone
            WHERE 
                reservation.status IN (?, ?)';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $errorResponse = ["message" => "Failed to prepare SQL statement", "error" => $conn->error];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }

    $stmt->bind_param('ss', $status1, $status2); // Binding both statuses
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();

    while ($row = $result->fetch_assoc()) {
        $reservationId = $row['id_reservation'];
        $accountId = $row['id'];

        // ถ้าไม่มีข้อมูล reservation นี้ใน array ให้เพิ่ม
        if (!isset($data[$reservationId])) {
            $data[$reservationId] = array(
                'reservation' => array(
                    'id_reservation' => $row['id_reservation'],
                    'date_reserva' => $row['date_reserva'], // วันที่เป็นภาษาไทย
                    'date_payment' => $row['date_payment'], // วันที่เป็นภาษาไทย
                    'sell' => $row['sell'],
                    'sell_two' => $row['sell_two'], // Include sell_two
                    'sell_three' => $row['sell_three'], // Include sell_three
                    'sell_four' => $row['sell_four'], // Include sell_four
                    'img_slip' => $row['img_slip'],
                    'status' => $row['status']
                ),
                'account' => array()
            );
        }

        // ถ้าไม่มีข้อมูล account นี้ใน reservation นี้ให้เพิ่ม
        if (!isset($data[$reservationId]['account'][$accountId])) {
            $data[$reservationId]['account'][$accountId] = array(
                'id' => $row['id'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'boot' => array() // Initialize boots array
            );
        }

        // เพิ่มข้อมูล boot ที่เกี่ยวข้องพร้อมข้อมูล zone
        $data[$reservationId]['account'][$accountId]['boot'][] = array(
            'id_zone' => $row['id_zone'],
            'number_boot' => $row['number_boot'],
            'price' => $row['price'],
            'zone' => array(
                'id_zone' => $row['id_zone'],
                'name_zone' => $row['name_zone'],
            )
        );
    }

    // เปลี่ยนค่า key ให้เป็น array ของ object
    $responseData = [];
    foreach ($data as $reservation) {
        $reservation['account'] = array_values($reservation['account']);
        $responseData[] = $reservation;
    }

    $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});

// $app->get('/list-reservation-approve', function (Request $request, Response $response) {
//     $conn = $GLOBALS['conn'];
//     $conn->query("SET lc_time_names = 'th_TH'");
//     // Update the SQL query to include additional sell fields and date formatting
//     $sql = 'SELECT 
//                 reservation.*, 
//                 boot.*, 
//                 account.*, 
//                 zone.*, 
//                 DATE_FORMAT(DATE_ADD(reservation.date_reserva, INTERVAL 543 YEAR), "%Y-%m-%d") AS date_reserva, 
//                 DATE_FORMAT(DATE_ADD(reservation.date_payment, INTERVAL 543 YEAR), "%Y-%m-%d") AS date_payment,
//                 reservation.sell_two, 
//                 reservation.sell_three, 
//                 reservation.sell_four 
//             FROM reservation 
//             JOIN boot ON reservation.id_reservation = boot.id_reservation
//             JOIN account ON reservation.id = account.id
//             JOIN zone ON boot.id_zone = zone.id_zone
//             WHERE reservation.status IN (?, ?)';

//     $stmt = $conn->prepare($sql);

//     // Check if the SQL statement was prepared successfully
//     if (!$stmt) {
//         $errorResponse = ["message" => "Failed to prepare SQL statement", "error" => $conn->error];
//         $response->getBody()->write(json_encode($errorResponse));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(500);
//     }

//     // Define the statuses for the reservation
//     $status1 = 'ชําระเงินสดเเล้ว';
//     $status2 = 'ชําระเงินเเล้ว';
//     $stmt->bind_param('ss', $status1, $status2); // Binding both statuses
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $data = array();

//     while ($row = $result->fetch_assoc()) {
//         $reservationId = $row['id_reservation'];
//         $accountId = $row['id'];

//         // If this reservation is not yet in the array, add it
//         if (!isset($data[$reservationId])) {
//             $data[$reservationId] = array(
//                 'reservation' => array(
//                     'id_reservation' => $row['id_reservation'],
//                     'date_reserva' => $row['date_reserva'], // Updated to formatted date
//                     'date_payment' => $row['date_payment'], // Updated to formatted date
//                     'sell' => $row['sell'],
//                     'sell_two' => $row['sell_two'], // Include sell_two
//                     'sell_three' => $row['sell_three'], // Include sell_three
//                     'sell_four' => $row['sell_four'], // Include sell_four
//                     'img_slip' => $row['img_slip'],
//                     'status' => $row['status']
//                 ),
//                 'account' => array()
//             );
//         }

//         // If this account is not yet in the reservation, add it
//         if (!isset($data[$reservationId]['account'][$accountId])) {
//             $data[$reservationId]['account'][$accountId] = array(
//                 'id' => $row['id'],
//                 'firstname' => $row['firstname'],
//                 'lastname' => $row['lastname'],
//                 'email' => $row['email'],
//                 'boot' => array() // Initialize boots array
//             );
//         }

//         // Add the related boot information along with zone data
//         $data[$reservationId]['account'][$accountId]['boot'][] = array(
//             'id_zone' => $row['id_zone'],
//             'number_boot' => $row['number_boot'],
//             'price' => $row['price'],
//             'zone' => array(
//                 'id_zone' => $row['id_zone'],
//                 'name_zone' => $row['name_zone'],
//             )
//         );
//     }

//     // Change keys to be an array of objects
//     $responseData = [];
//     foreach ($data as $reservation) {
//         $reservation['account'] = array_values($reservation['account']);
//         $responseData[] = $reservation;
//     }

//     $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
//     return $response
//         ->withHeader('Content-Type', 'application/json; charset=utf-8')
//         ->withStatus(200);
// });




$app->get('/reservation-approve-cash', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];

    // ตั้งค่า locale ให้เป็นภาษาไทย
    $conn->query("SET lc_time_names = 'th_TH'");

    // Update the SQL query to format dates and include additional sell fields
    $sql = 'SELECT 
                reservation.id_reservation,
                DATE_FORMAT(DATE_ADD(reservation.date_reserva, INTERVAL 543 YEAR), "%e %M %Y") AS date_reserva,
                DATE_FORMAT(DATE_ADD(reservation.date_payment, INTERVAL 543 YEAR), "%e %M %Y") AS date_payment,
                reservation.sell,
                reservation.sell_two,
                reservation.sell_three,
                reservation.sell_four,
                reservation.img_slip,
                reservation.status,
                boot.id_boot,
                boot.number_boot,
                boot.price,
                account.id,
                account.firstname,
                account.lastname,
                account.email,
                zone.id_zone,
                zone.name_zone
            FROM 
                reservation 
            JOIN 
                boot ON reservation.id_reservation = boot.id_reservation
            JOIN 
                account ON reservation.id = account.id
            JOIN 
                zone ON boot.id_zone = zone.id_zone
            WHERE 
                reservation.status = ?';

    $stmt = $conn->prepare($sql);

    // ตรวจสอบว่าคำสั่ง SQL ถูกเตรียมสำเร็จหรือไม่
    if (!$stmt) {
        $errorResponse = ["message" => "Failed to prepare SQL statement", "error" => $conn->error];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }

    $status = 'อยู่ในระหว่างการตรวจสอบชําระเงินสด';
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();

    while ($row = $result->fetch_assoc()) {
        $reservationId = $row['id_reservation'];
        $accountId = $row['id'];
        
        // ถ้าไม่มีข้อมูล reservation นี้ใน array ให้เพิ่ม
        if (!isset($data[$reservationId])) {
            $data[$reservationId] = array(
                'reservation' => array(
                    'id_reservation' => $row['id_reservation'],
                    'date_reserva' => $row['date_reserva'], // วันที่เป็นภาษาไทย
                    'date_payment' => $row['date_payment'], // วันที่เป็นภาษาไทย
                    'sell' => $row['sell'],
                    'sell_two' => $row['sell_two'], // Include sell_two
                    'sell_three' => $row['sell_three'], // Include sell_three
                    'sell_four' => $row['sell_four'], // Include sell_four
                    'img_slip' => $row['img_slip'],
                    'status' => $row['status']
                ),
                'account' => array()
            );
        }

        // ถ้าไม่มีข้อมูล account นี้ใน reservation นี้ให้เพิ่ม
        if (!isset($data[$reservationId]['account'][$accountId])) {
            $data[$reservationId]['account'][$accountId] = array(
                'id' => $row['id'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'boot' => array() // Initialize boots array
            );
        }

        // เพิ่มข้อมูล boot ที่เกี่ยวข้องพร้อมข้อมูล zone
        $data[$reservationId]['account'][$accountId]['boot'][] = array(
            'id_zone' => $row['id_zone'],
            'number_boot' => $row['number_boot'],
            'price' => $row['price'],
            'zone' => array(
                'id_zone' => $row['id_zone'],
                'name_zone' => $row['name_zone'],
            )
        );
    }

    // เปลี่ยนค่า key ให้เป็น array ของ object
    $responseData = [];
    foreach ($data as $reservation) {
        $reservation['account'] = array_values($reservation['account']);
        $responseData[] = $reservation;
    }

    $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});


// $app->get('/list-reservation-NotPay', function (Request $request, Response $response) {
//     $conn = $GLOBALS['conn'];
//     $sql = 'SELECT reservation.*, boot.*, account.* FROM reservation 
//             JOIN boot ON reservation.id_reservation = boot.id_reservation
//             JOIN account ON reservation.id = account.id
//             WHERE reservation.status = ?'; // Add WHERE clause to filter by status

//     $stmt = $conn->prepare($sql);
//     $status = 'ยังไม่ชำระเงิน'; // Define the status to filter by
//     $stmt->bind_param('s', $status); // Bind the status parameter
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $data = array();
//     while ($row = $result->fetch_assoc()) { // Use fetch_assoc() to fetch rows
//         array_push($data, $row);
//     }

//     $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
//     return $response
//         ->withHeader('Content-Type', 'application/json; charset=utf-8')
//         ->withStatus(200);
// });
$app->get('/list-reservation-NotPay', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];
    
    // ตั้งค่า locale ให้เป็นภาษาไทย
    $conn->query("SET lc_time_names = 'th_TH'");
    
    // Add sell_two, sell_three, and sell_four in the SELECT statement
    $sql = 'SELECT 
                reservation.id_reservation,
                DATE_FORMAT(DATE_ADD(reservation.date_reserva, INTERVAL 543 YEAR), "%e %M %Y") AS date_reserva,
                DATE_FORMAT(DATE_ADD(reservation.date_payment, INTERVAL 543 YEAR), "%e %M %Y") AS date_payment,
                reservation.sell,
                reservation.sell_two, -- Added
                reservation.sell_three, -- Added
                reservation.sell_four, -- Added
                reservation.img_slip,
                reservation.status,
                boot.id_boot,
                boot.number_boot,
                boot.price,
                account.id,
                account.firstname,
                account.lastname,
                account.email,
                zone.id_zone,
                zone.name_zone
            FROM 
                reservation 
            JOIN 
                boot ON reservation.id_reservation = boot.id_reservation
            JOIN 
                account ON reservation.id = account.id
            JOIN 
                zone ON boot.id_zone = zone.id_zone
            WHERE 
                reservation.status = ?';

    $stmt = $conn->prepare($sql);

    // ตรวจสอบว่าคำสั่ง SQL ถูกเตรียมสำเร็จหรือไม่
    if (!$stmt) {
        $errorResponse = ["message" => "Failed to prepare SQL statement", "error" => $conn->error];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }

    $status = 'ยังไม่ชำระเงิน';
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();

    while ($row = $result->fetch_assoc()) {
        $reservationId = $row['id_reservation'];
        $accountId = $row['id'];
        
        // ถ้าไม่มีข้อมูล reservation นี้ใน array ให้เพิ่ม
        if (!isset($data[$reservationId])) {
            $data[$reservationId] = array(
                'reservation' => array(
                    'id_reservation' => $row['id_reservation'],
                    'date_reserva' => $row['date_reserva'],
                    'date_payment' => $row['date_payment'],
                    'sell' => $row['sell'],
                    'sell_two' => $row['sell_two'], // Include sell_two
                    'sell_three' => $row['sell_three'], // Include sell_three
                    'sell_four' => $row['sell_four'], // Include sell_four
                    'img_slip' => $row['img_slip'],
                    'status' => $row['status']
                ),
                'account' => array()
            );
        }

        // ถ้าไม่มีข้อมูล account นี้ใน reservation นี้ให้เพิ่ม
        if (!isset($data[$reservationId]['account'][$accountId])) {
            $data[$reservationId]['account'][$accountId] = array(
                'id' => $row['id'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'boot' => array() // Initialize boots array
            );
        }

        // เพิ่มข้อมูล boot ที่เกี่ยวข้องพร้อมข้อมูล zone
        $data[$reservationId]['account'][$accountId]['boot'][] = array(
            'id_zone' => $row['id_zone'],
            'number_boot' => $row['number_boot'],
            'price' => $row['price'],
            'zone' => array(
                'id_zone' => $row['id_zone'],
                'name_zone' => $row['name_zone'],
            )
        );
    }

    // เปลี่ยนค่า key ให้เป็น array ของ object
    $responseData = [];
    foreach ($data as $reservation) {
        $reservation['account'] = array_values($reservation['account']);
        $responseData[] = $reservation;
    }

    $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});
$app->get('/list-reservation-payagain', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];
    
    // ตั้งค่า locale ให้เป็นภาษาไทย
    $conn->query("SET lc_time_names = 'th_TH'");
    
    // Add sell_two, sell_three, and sell_four in the SELECT statement
    $sql = 'SELECT 
                reservation.id_reservation,
                DATE_FORMAT(DATE_ADD(reservation.date_reserva, INTERVAL 543 YEAR), "%e %M %Y") AS date_reserva,
                DATE_FORMAT(DATE_ADD(reservation.date_payment, INTERVAL 543 YEAR), "%e %M %Y") AS date_payment,
                reservation.sell,
                reservation.sell_two, -- Added
                reservation.sell_three, -- Added
                reservation.sell_four, -- Added
                reservation.img_slip,
                reservation.status,
                boot.id_boot,
                boot.number_boot,
                boot.price,
                account.id,
                account.firstname,
                account.lastname,
                account.email,
                zone.id_zone,
                zone.name_zone
            FROM 
                reservation 
            JOIN 
                boot ON reservation.id_reservation = boot.id_reservation
            JOIN 
                account ON reservation.id = account.id
            JOIN 
                zone ON boot.id_zone = zone.id_zone
            WHERE 
                reservation.status = ?';

    $stmt = $conn->prepare($sql);

    // ตรวจสอบว่าคำสั่ง SQL ถูกเตรียมสำเร็จหรือไม่
    if (!$stmt) {
        $errorResponse = ["message" => "Failed to prepare SQL statement", "error" => $conn->error];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }

    $status = 'กรุณาชําระเงินอีกครั้ง';
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();

    while ($row = $result->fetch_assoc()) {
        $reservationId = $row['id_reservation'];
        $accountId = $row['id'];
        
        // ถ้าไม่มีข้อมูล reservation นี้ใน array ให้เพิ่ม
        if (!isset($data[$reservationId])) {
            $data[$reservationId] = array(
                'reservation' => array(
                    'id_reservation' => $row['id_reservation'],
                    'date_reserva' => $row['date_reserva'],
                    'date_payment' => $row['date_payment'],
                    'sell' => $row['sell'],
                    'sell_two' => $row['sell_two'], // Include sell_two
                    'sell_three' => $row['sell_three'], // Include sell_three
                    'sell_four' => $row['sell_four'], // Include sell_four
                    'img_slip' => $row['img_slip'],
                    'status' => $row['status']
                ),
                'account' => array()
            );
        }

        // ถ้าไม่มีข้อมูล account นี้ใน reservation นี้ให้เพิ่ม
        if (!isset($data[$reservationId]['account'][$accountId])) {
            $data[$reservationId]['account'][$accountId] = array(
                'id' => $row['id'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'boot' => array() // Initialize boots array
            );
        }

        // เพิ่มข้อมูล boot ที่เกี่ยวข้องพร้อมข้อมูล zone
        $data[$reservationId]['account'][$accountId]['boot'][] = array(
            'id_zone' => $row['id_zone'],
            'number_boot' => $row['number_boot'],
            'price' => $row['price'],
            'zone' => array(
                'id_zone' => $row['id_zone'],
                'name_zone' => $row['name_zone'],
            )
        );
    }

    // เปลี่ยนค่า key ให้เป็น array ของ object
    $responseData = [];
    foreach ($data as $reservation) {
        $reservation['account'] = array_values($reservation['account']);
        $responseData[] = $reservation;
    }

    $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});

$app->get('/reservation', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];
    $id = $request->getQueryParams()['id'];

    // Set the locale to Thai
    $conn->query("SET lc_time_names = 'th_TH'");

    $sql = 'SELECT 
                reservation.id_reservation, 
                reservation.id,
                DATE_FORMAT(DATE_ADD(reservation.date_reserva, INTERVAL 543 YEAR), "%e %M %Y") AS date_reserva,
                DATE_FORMAT(DATE_ADD(reservation.date_payment, INTERVAL 543 YEAR), "%e %M %Y") AS date_payment,
                reservation.sell, 
                reservation.sell_two,  -- เพิ่ม sell_two
                reservation.sell_three,  -- เพิ่ม sell_three
                reservation.sell_four,  -- เพิ่ม sell_four
                reservation.img_slip, 
                reservation.status, 
                SUM(boot.price) AS total_price,
                COUNT(boot.id_boot) AS boot_count,
                GROUP_CONCAT(
                    JSON_OBJECT(
                        "id_boot", boot.id_boot,
                        "number_boot", boot.number_boot,
                        "status_boot", boot.status_boot,
                        "img_boot", boot.img_boot,
                        "price", boot.price,
                        "boot_size", boot.boot_size
                    )
                ) AS boots,
                JSON_OBJECT(
                    "id_zone", zone.id_zone,
                    "name_zone", zone.name_zone,
                    "img_showzone", zone.img_showzone,
                    "img_mapzone", zone.img_mapzone,
                    "detail_zone", zone.detail_zone
                ) AS zone_details
            FROM 
                reservation 
            JOIN 
                boot ON reservation.id_reservation = boot.id_reservation 
            JOIN
                zone ON boot.id_zone = zone.id_zone
            WHERE 
                reservation.id = ?
            GROUP BY 
                reservation.id_reservation, zone.id_zone';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();
    while ($row = $result->fetch_assoc()) {
        $row['boots'] = json_decode('[' . $row['boots'] . ']', true);
        $row['zone_details'] = json_decode($row['zone_details'], true);
        $data[] = $row;
    }

    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});




$app->get('/reservation-payment', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];
    $id_reservation = $request->getQueryParams()['id_reservation'];

    // Set the locale to Thai
    $conn->query("SET lc_time_names = 'th_TH'");

    $sql = 'SELECT 
                reservation.id_reservation, 
                reservation.id,
                DATE_FORMAT(reservation.date_reserva, "%d %M %Y") AS date_reserva,
                DATE_FORMAT(reservation.date_payment, "%d %M %Y") AS date_payment,
                reservation.sell, 
                reservation.img_slip, 
                reservation.status, 
                SUM(boot.price) AS total_price,
                GROUP_CONCAT(
                    JSON_OBJECT(
                        "id_boot", boot.id_boot,
                        "number_boot", boot.number_boot,
                        "status_boot", boot.status_boot,
                        "img_boot", boot.img_boot,
                        "price", boot.price,
                        "boot_size", boot.boot_size
                    )
                ) AS boots,
                JSON_OBJECT(
                    "id_zone", zone.id_zone,
                    "name_zone", zone.name_zone,
                    "img_showzone", zone.img_showzone,
                    "img_mapzone", zone.img_mapzone,
                    "detail_zone", zone.detail_zone
                ) AS zone_details
            FROM 
                reservation 
            JOIN 
                boot ON reservation.id_reservation = boot.id_reservation 
            JOIN
                zone ON boot.id_zone = zone.id_zone
            WHERE 
                reservation.id_reservation = ?
            GROUP BY 
                reservation.id_reservation, zone.id_zone'; 

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_reservation);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();
    while ($row = $result->fetch_assoc()) {
        $row['boots'] = json_decode('[' . $row['boots'] . ']', true); 
        $row['zone_details'] = json_decode($row['zone_details'], true); 
        $data[] = $row;
    }

    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});

// $app->get('/reservation', function (Request $request, Response $response) {
//     $conn = $GLOBALS['conn'];
//     $id = $request->getQueryParams()['id']; 
//     $sql = 'SELECT reservation.id_reservation, reservation.id, reservation.date_reserva, reservation.date_payment, reservation.sell, reservation.img_slip, reservation.status, 
//             SUM(boot.price) AS total_price,
//             GROUP_CONCAT(
//                 JSON_OBJECT(
//                     "id_boot", boot.id_boot,
//                     "number_boot", boot.number_boot,
//                     "status_boot", boot.status_boot,
//                     "img_boot", boot.img_boot,
//                     "price", boot.price,
//                     "boot_size", boot.boot_size,
//                     "id_zone", boot.id_zone
//                 )
//             ) AS boots
//             FROM reservation 
//             JOIN boot ON reservation.id_reservation = boot.id_reservation 
//             WHERE reservation.id = ?
//             GROUP BY reservation.id_reservation'; 
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('i', $id); 
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $data = array();
//     while ($row = $result->fetch_assoc()) {
//         $row['boots'] = json_decode('[' . $row['boots'] . ']', true); 
//         $data[] = $row;
//     }

//     $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
//     return $response
//         ->withHeader('Content-Type', 'application/json; charset=utf-8')
//         ->withStatus(200);
// });

// $app->get('/reservation/{id}', function (Request $request, Response $response, $args) {
//     $conn = $GLOBALS['conn'];
//     $id = $args['id']; // รับค่า id ที่ส่งมาจาก URL
//     $sql = 'SELECT reservation.id_reservation, reservation.id, reservation.date_reserva, reservation.date_payment, reservation.sell, reservation.img_slip, reservation.status,
//     GROUP_CONCAT(JSON_OBJECT("id_boot", boot.id_boot, "number_boot", boot.number_boot, "status_boot", boot.status_boot, "img_boot", boot.img_boot, "price", boot.price, "boot_size", boot.boot_size, "id_zone", boot.id_zone)) AS boots,
//     SUM(boot.price) AS total_price
//     FROM reservation
//     JOIN boot ON reservation.id_reservation = boot.id_reservation
//     WHERE reservation.id = ?
//     GROUP BY reservation.id_reservation'; // เพิ่ม GROUP BY เพื่อรวมข้อมูลเรือให้อยู่ในแถวเดียวกัน
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('i', $id); // ผูกค่า id กับตัวแปรในคำสั่ง SQL
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $data = array();
//     foreach ($result as $row) {
//         $row['boots'] = json_decode('[' . $row['boots'] . ']', true); // แปลงข้อมูล boots จาก JSON string เป็น array
//         array_push($data, $row);
//     }

//     $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
//     return $response
//         ->withHeader('Content-Type', 'application/json; charset=utf-8')
//         ->withStatus(200);
// });
// $app->get('/reservation/{id}', function (Request $request, Response $response, array $args) {
//     $conn = $GLOBALS['conn'];
//     $id = $args['id'];
//     $sql = 'SELECT * FROM reservation  WHERE id = ? ';
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('i', $id);
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $data = array();
//     foreach ($result as $row) {
//         array_push($data, $row);
//     }

//     $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
//     return $response
//         ->withHeader('Content-Type', 'application/json; charset=utf-8')
//         ->withStatus(200);
// });

// -----------------------เรียกข้อมูลการจองจาก id account ทั้งหมด -------------------
// $app->get('/reservation/{id}', function (Request $request, Response $response, array $args) {
    
//     $conn = $GLOBALS['conn'];
//     $id = $args['id'];
//     $sql = 'SELECT reservation.*, boot.id_boot, boot.number_boot, boot.status_boot, boot.img_boot, boot.price, boot.boot_size, boot.id_zone,zone.name_zone
//             FROM reservation 
//             JOIN boot ON reservation.id_reservation = boot.id_reservation
//             INNER JOIN zone ON boot.id_zone = zone.id_zone
//             WHERE reservation.id = ?';
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('i', $id);
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $data = array();
//     foreach ($result as $row) {
//         array_push($data, $row);
//     }

//     $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
//     return $response
//         ->withHeader('Content-Type', 'application/json; charset=utf-8')
//         ->withStatus(200);
// });


?>