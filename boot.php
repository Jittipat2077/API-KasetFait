<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->post('/update-bootdetail', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];
    // Directory for image uploads
    $imgPath = 'C:/Users/aleny/Desktop/Final/New/my-project/my-project/src/assets/img/boot/';

    // Function to handle file uploads
    function handleUpload($fileKey, $imgPath) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
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
    // Handle img_boot file upload
    $imgBoot = handleUpload('img_boot', $imgPath);

    // Set status_boot to an empty string if it's not provided
    $statusBoot = isset($_POST['status_boot']) ? $_POST['status_boot'] : 'ว่าง';

    // Prepare and execute SQL statement
    $sql = "UPDATE boot SET 
                number_boot = ?, 
                status_boot = ?, 
                img_boot = IFNULL(?, img_boot), 
                price = ?, 
                boot_size = ? 
            WHERE id_boot = ?";
    $stmt = $conn->prepare($sql);

    // Ensure all required POST fields are set and bind parameters
    $stmt->bind_param(
        'sssssi', 
        $_POST['number_boot'], 
        $statusBoot, 
        $imgBoot, 
        $_POST['price'], 
        $_POST['boot_size'], 
        $_POST['id_boot']
    );
    $stmt->execute();
    $affected = $stmt->affected_rows;
    if ($affected > 0) {
        $data = ["affected_rows" => $affected];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); // Return 200 OK status
    } else {
        // If data update fails
        $errorResponse = ["message" => "Failed to update boot entry"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Return 500 Internal Server Error status
    }
});

$app->delete('/delete-boot/{id_boot}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // รับ ID จากพารามิเตอร์ของเส้นทาง
    $BootId = $args['id_boot'];

    // เริ่มต้นการทำธุรกรรม (transaction)
    $conn->begin_transaction();

    try {
        // ลบข้อมูลจากตาราง reservation ที่เชื่อมโยงกับตาราง boot ผ่าน id_reservation ที่มี id_zone ตรงกัน
      
        // ลบข้อมูลจากตารางอื่น ๆ ที่เชื่อมโยงกับ reservation ที่มี id_reservation ตรงกับ id_boot ที่มี id_zone ตรงกัน
        // ใส่โค้ดเพิ่มเติมที่นี่เพื่อลบข้อมูลในตารางอื่น ๆ ที่เชื่อมโยงกับ reservation

        // ลบข้อมูลจากตาราง boot ที่มี id_zone ตรงกัน
        $sqlBoot = "DELETE FROM boot WHERE id_boot = ?";
        $stmtBoot = $conn->prepare($sqlBoot);
        $stmtBoot->bind_param('i', $BootId );
        $stmtBoot->execute();

        // ลบข้อมูลจากตาราง zone
 
        
        // ตรวจสอบจำนวนแถวที่ได้รับผลกระทบ
        $affectedBoot = $stmtBoot->affected_rows;

        if ($affectedBoot > 0) {
            // ยืนยันการทำธุรกรรม (commit transaction)
            $conn->commit();

            $data = [
                "message" => "Boot deleted successfully",
                "affected_Boot_rows" =>  $affectedBoot
            ];
            $response->getBody()->write(json_encode($data));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200); // ส่งกลับสถานะ 200 OK
        } else {
            // ยกเลิกการทำธุรกรรม (rollback transaction) หากล้มเหลว
            $conn->rollback();

            $errorResponse = ["message" => "Failed to delete zone"];
            $response->getBody()->write(json_encode($errorResponse));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500); // ส่งกลับสถานะ 500 Internal Server Error
        }
    } catch (Exception $e) {
        // ยกเลิกการทำธุรกรรม (rollback transaction) หากเกิดข้อผิดพลาด
        $conn->rollback();

        $errorResponse = ["message" => "An error occurred while deleting zone", "error" => $e->getMessage()];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // ส่งกลับสถานะ 500 Internal Server Error
    }
});

$app->post('/add-boot', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // Directory for image uploads
    $imgPath = 'C:/Users/aleny/Desktop/Final/New/my-project/my-project/src/assets/img/boot/';

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

    // Handle img_boot file upload
    $imgBoot = handleUpload('img_boot', $imgPath);

    // Set status_boot to an empty string if it's not provided
    $statusBoot = isset($_POST['status_boot']) ? $_POST['status_boot'] : 'ว่าง';

    // Prepare and execute SQL statement
    $sql = "INSERT INTO boot (number_boot, status_boot, img_boot, price, boot_size, id_zone) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    // Ensure all required POST fields are set and bind parameters
    $stmt->bind_param(
        'sssssi', 
        $_POST['number_boot'], 
        $statusBoot, 
        $imgBoot, 
        $_POST['price'], 
        $_POST['boot_size'], 
        $_POST['id_zone']
    );

    $stmt->execute();

    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        $data = ["affected_rows" => $affected, "last_idx" => $conn->insert_id];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); // Return 200 OK status
    } else {
        // If data insertion fails
        $errorResponse = ["message" => "Failed to create boot entry"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // Return 500 Internal Server Error status
    }
});

?>