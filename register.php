<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;




// $app->post('/reset-password', function (Request $request, Response $response, $args) {
//     $data = $request->getParsedBody();
//     $token = $data['token'] ?? null;
//     $newPassword = $data['new_password'] ?? null;

//     if (empty($token) || empty($newPassword)) {
//         $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Token and new password are required.']));
//         return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
//     }

//     $conn = $GLOBALS['connect'];

//     // Verify token
//     $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND created_at > NOW() - INTERVAL 1 HOUR");
//     $stmt->bind_param("s", $token);
//     $stmt->execute();
//     $result = $stmt->get_result();

//     if ($result->num_rows === 0) {
//         $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Invalid or expired token.']));
//         return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
//     }

//     $email = $result->fetch_assoc()['email'];

//     // Update password
//     $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

//     $stmt = $conn->prepare("UPDATE account SET password = ? WHERE email = ?");
//     $stmt->bind_param("ss", $hashedPassword, $email);
//     $stmt->execute();

//     // Delete the used token
//     $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
//     $stmt->bind_param("s", $token);
//     $stmt->execute();

//     $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Password has been reset successfully.']));
//     return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
// });
// $app->post('/edit-password', function (Request $request, Response $response, $args) {
//     $json = $request->getBody();
//     $jsonData = json_decode($json, true);
//     $conn = $GLOBALS['conn'];

//     // Check if the ID and password are provided
//     if (!isset($jsonData['id']) || !isset($jsonData['password'])) {
//         $errorResponse = ["message" => "User ID and password are required"];
//         $response->getBody()->write(json_encode($errorResponse));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(400); // Return a 400 Bad Request status
//     }

//     $userId = $jsonData['id'];

//     // Proceed with updating the user's password
//     $sql = "UPDATE account SET password = ? WHERE id = ?";

//     // Hash the new password
//     $hashedPassword = password_hash($jsonData['password'], PASSWORD_DEFAULT);

//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('si', $hashedPassword, $userId);
//     $stmt->execute();

//     $affected = $stmt->affected_rows;

//     if ($affected > 0) {
//         $data = ["affected_rows" => $affected, "user_id" => $userId];
//         $response->getBody()->write(json_encode($data));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(200); // Return a 200 OK status
//     } else {
//         // If the update failed for any reason
//         $errorResponse = ["message" => "Failed to update user password"];
//         $response->getBody()->write(json_encode($errorResponse));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(500); // Return a 500 Internal Server Error status
//     }
// });
$app->get('/add-user/{id}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];
    $id_account = $args['id'] ?? null;
    if ($id_account === null) {
        $errorResponse = ["message" => "id_account is required"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400); 
    }
    $sql = 'SELECT * FROM account WHERE id = ?'; 
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $id_account);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();
    while ($row = $result->fetch_assoc()) {
        array_push($data, $row);
    }
    if (empty($data)) {
        $errorResponse = ["message" => "No account found with the provided id_account"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404); // Return a 404 Not Found status
    }
    $updateAccountSql = 'UPDATE account SET role = ? WHERE id = ?';
    $updateAccountStmt = $conn->prepare($updateAccountSql);
    $newRole = 'user'; 
    $updateAccountStmt->bind_param('ss', $newRole, $id_account);
    $updateAccountStmt->execute();
    $stmt->execute();
    $result = $stmt->get_result();
    $updatedData = array();
    while ($row = $result->fetch_assoc()) {
        array_push($updatedData, $row);
    }
    $response->getBody()->write(json_encode($updatedData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});

$app->get('/add-admin/{id}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];
    $id_account = $args['id'] ?? null;

    if ($id_account === null) {
        $errorResponse = ["message" => "id_account is required"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400); 
    }
    $sql = 'SELECT * FROM account WHERE id = ?'; // Filter by id_account
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $id_account);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();
    while ($row = $result->fetch_assoc()) {
        array_push($data, $row);
    }
    if (empty($data)) {
        $errorResponse = ["message" => "No account found with the provided id_account"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404); 
    }
    $updateAccountSql = 'UPDATE account SET role = ? WHERE id = ?';
    $updateAccountStmt = $conn->prepare($updateAccountSql);
    $newRole = 'admin'; // new role for account
    $updateAccountStmt->bind_param('ss', $newRole, $id_account);
    $updateAccountStmt->execute();
    $stmt->execute();
    $result = $stmt->get_result();
    $updatedData = array();
    while ($row = $result->fetch_assoc()) {
        array_push($updatedData, $row);
    }
    $response->getBody()->write(json_encode($updatedData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});


$app->post('/update-boot', function (Request $request, Response $response, $args) {
    $json = $request->getBody();
    $jsonData = json_decode($json, true);
    $conn = $GLOBALS['conn'];

    foreach ($jsonData as $boot) {
        $idBoot = $boot['id_boot']; // Extract the boot ID from the data
        $idReservation = $boot['id_reservation']; // Extract the reservation ID from the data

        // Proceed with updating the boot
        $sql = "UPDATE boot SET id_reservation=?, status_boot='อยู่ระหว่างตรวจสอบ' WHERE id_boot=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $idReservation, $idBoot); // Assuming 'id_reservation' is of type integer and 'id_boot' is of type integer
        $stmt->execute();

        $affected = $stmt->affected_rows;

        if ($affected <= 0) {
            // If the update failed for any reason for a specific boot, return an error response
            $errorResponse = ["message" => "Failed to update boot with ID $idBoot"];
            $response->getBody()->write(json_encode($errorResponse));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500); // Return a 500 Internal Server Error status
        }
    }

    // If all boots are updated successfully, return a success response
    $data = ["message" => "All boots updated successfully"];
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200); // Return a 200 OK status
});
$app->post('/edit-password', function (Request $request, Response $response, $args) {
    $json = $request->getBody();
    $jsonData = json_decode($json, true);
    $conn = $GLOBALS['conn'];

    // Check if the ID and password are provided
    if (!isset($jsonData['id']) || !isset($jsonData['password'])) {
        $errorResponse = ["message" => "User ID and password are required"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400); // Return a 400 Bad Request status
    }

    $userId = $jsonData['id'];

    // Proceed with updating the user's password
    $sql = "UPDATE account SET password = ? WHERE id = ?";

    // Hash the new password
    $hashedPassword = password_hash($jsonData['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $hashedPassword, $userId);
    $stmt->execute();

    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        $data = ["affected_rows" => $affected, "user_id" => $userId];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); // Return a 200 OK status
    } else {
        // If the update failed for any reason
        $errorResponse = ["message" => "Failed to update user password"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Return a 500 Internal Server Error status
    }
});

$app->get('/list-all-user', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];
    $sql = 'SELECT * FROM account';
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

$app->get('/user/{id}', function (Request $request, Response $response, array $args) {
    $conn = $GLOBALS['conn'];
    $userId = $args['id'];

    $sql = 'SELECT * FROM account WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();

    if (!$userData) {
        return $response->withStatus(404)->getBody()->write("User not found");
    }

    $response->getBody()->write(json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});

$app->post('/edit-user', function (Request $request, Response $response, $args) {
    $json = $request->getBody();
    $jsonData = json_decode($json, true);
    $conn = $GLOBALS['conn'];

    // Check if the ID is provided
    if (!isset($jsonData['id'])) {
        $errorResponse = ["message" => "User ID is required"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400); // Return a 400 Bad Request status
    }

    $userId = $jsonData['id'];

    // Check if the user exists
    $checkUserSql = "SELECT COUNT(*) AS user_count FROM account WHERE id = ?";
    $checkUserStmt = $conn->prepare($checkUserSql);
    $checkUserStmt->bind_param('i', $userId);
    $checkUserStmt->execute();
    $checkUserResult = $checkUserStmt->get_result();
    $userCount = $checkUserResult->fetch_assoc()['user_count'];

    // If the user does not exist, return an error response
    if ($userCount == 0) {
        $errorResponse = ["message" => "User not found"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404); // Return a 404 Not Found status
    }

    // Check if the email is already in use by another user
    $checkEmailSql = "SELECT COUNT(*) AS email_count FROM account WHERE email = ? AND id != ?";
    $checkEmailStmt = $conn->prepare($checkEmailSql);
    $checkEmailStmt->bind_param('si', $jsonData['email'], $userId);
    $checkEmailStmt->execute();
    $checkEmailResult = $checkEmailStmt->get_result();
    $emailCount = $checkEmailResult->fetch_assoc()['email_count'];

    // If the email is already in use by another user, return an error response
    if ($emailCount > 0) {
        $errorResponse = ["message" => "Email address already in use"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400); // Return a 400 Bad Request status
    }

    // Proceed with updating the user (excluding the password)
    $sql = "UPDATE account SET firstname = ?, lastname = ?, email = ?, tel = ?, role = ? WHERE id = ?";

    // Set role to 'user' if not provided
    $role = isset($jsonData['role']) ? $jsonData['role'] : 'user';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssi', $jsonData['firstname'], $jsonData['lastname'], $jsonData['email'], $jsonData['tel'], $role, $userId);
    $stmt->execute();

    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        $data = ["affected_rows" => $affected, "user_id" => $userId];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); // Return a 200 OK status
    } else {
        // If the update failed for any reason
        $errorResponse = ["message" => "Failed to update user"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Return a 500 Internal Server Error status
    }
});
$app->post('/user', function (Request $request, Response $response, $args) {
    $json = $request->getBody();
    $jsonData = json_decode($json, true);
    $conn = $GLOBALS['conn'];

    // Check if the email already exists
    $checkEmailSql = "SELECT COUNT(*) AS email_count FROM account WHERE email = ?";
    $checkEmailStmt = $conn->prepare($checkEmailSql);
    $checkEmailStmt->bind_param('s', $jsonData['email']);
    $checkEmailStmt->execute();
    $checkEmailResult = $checkEmailStmt->get_result();
    $emailCount = $checkEmailResult->fetch_assoc()['email_count'];

    // If the email already exists, return an error response
    if ($emailCount > 0) {
        $errorResponse = ["message" => "Email address already in use"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400); // Return a 400 Bad Request status
    }
    // Proceed with inserting the new user
    $sql = "INSERT INTO account(firstname, lastname, email, password, tel, role) VALUES (?,?,?,?,?,?)";
    $hashedPassword = password_hash($jsonData['password'], PASSWORD_DEFAULT);

    // Set role to 'user' if not provided
    $role = isset($jsonData['role']) ? $jsonData['role'] : 'user';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssss', $jsonData['firstname'], $jsonData['lastname'], $jsonData['email'], $hashedPassword, $jsonData['tel'], $role);
    $stmt->execute();

    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        $data = ["affected_rows" => $affected, "last_idx" => $conn->insert_id];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); // Return a 200 OK status
    } else {
        // If the insert failed for any reason other than duplicate email
        $errorResponse = ["message" => "Failed to create user"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Return a 500 Internal Server Error status
    }
});

// $app->post('/user', function (Request $request, Response $response, $args) {
//     $formData = $request->getParsedBody();
//     $conn = $GLOBALS['conn'];

//     // Check if the email already exists
//     $checkEmailSql = "SELECT COUNT(*) FROM `account` WHERE `email` = ?";
//     $checkEmailStmt = $conn->prepare($checkEmailSql);
//     $checkEmailStmt->bind_param('s', $formData['email']);
//     $checkEmailStmt->execute();
//     $checkEmailResult = $checkEmailStmt->get_result();
//     $emailExists = $checkEmailResult->fetch_assoc()['COUNT(*)'];

//     if ($emailExists > 0) {
//         $errorResponse = ["message" => "Email address already in use"];
//         $response->getBody()->write(json_encode($errorResponse));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(400); // Return a 400 Bad Request status
//     }

//     $sql = "INSERT INTO `account`(`firstname`, `lastname`, `email`, `password`, `tel`, `role`) VALUES (?,?,?,?,?,?)";
//     $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('ssssss',$formData['firstname'], $formData['lastname'], $formData['email'], $hashedPassword, $formData['tel'], $formData['role']);
//     $stmt->execute();

//     $affected = $stmt->affected_rows;
//     if ($affected > 0) {
//         $data = ["affected_rows" => $affected, "last_idx" => $conn->insert_id];
//         $response->getBody()->write(json_encode($data));
//         return $response
//             ->withHeader('Content-Type', 'application/json')
//             ->withStatus(200);
//     }
// });


$app->post('/login', function (Request $request, Response $response, $args) {
    $json = $request->getBody();
    $jsonData = json_decode($json, true);
    $email = $jsonData['email'];
    $password = $jsonData['password'];

    $conn = $GLOBALS['conn'];
    $sql = 'SELECT * FROM account WHERE email = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $role = $user['role'];

            if ($role === 'user') {
                $data = ["message" => "เข้าสู่ระบบสำเร็จ", "user" => $user, "redirect" => "main"];
            } elseif ($role === 'admin') {
                $data = ["message" => "เข้าสู่ระบบสำเร็จ", "user" => $user, "redirect" => "dashboard"];
            } else {
                $data = ["message" => "ไม่พบข้อมูล"];
            }

            $response->getBody()->write(json_encode($data));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } else {
            $data = ["message" => "อีเมลหรือรหัสผ่านไม่ถูกต้อง"];
            $response->getBody()->write(json_encode($data));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401);
        }
    } else {
        $data = ["message" => "ไม่พบข้อมูล"];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
});

$app->post('/login1', function (Request $request, Response $response, $args) {
    $json = $request->getBody();
    $jsonData = json_decode($json, true);
    $email = $jsonData['email'];
    $password = $jsonData['password'];

    $conn = $GLOBALS['conn'];
    $sql = 'SELECT * FROM account WHERE email = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $role = $user['role'];

            if ($role === 'user') {
                $data = ["message" => "เข้าสู่ระบบสำเร็จ", "user" => $user, "redirect" => "main"];
            } elseif ($role === 'admin') {
                $data = ["message" => "เข้าสู่ระบบสำเร็จ", "user" => $user, "redirect" => "dashboard"];
            } else {
                $data = ["message" => "ไม่พบข้อมูล"];
            }

            $response->getBody()->write(json_encode($data));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } else {
            $data = ["message" => "อีเมลหรือรหัสผ่านไม่ถูกต้อง"];
            $response->getBody()->write(json_encode($data));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401);
        }
    } else {
        $data = ["message" => "ไม่พบข้อมูล"];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
});