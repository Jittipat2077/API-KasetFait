<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


$app->post('/update-zone', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];
    // กำหนดไดเรกทอรีสำหรับการอัปโหลดรูปภาพ
    // $imgPath = 'C:/Users/aleny/Desktop/Final/New/my-project/my-project/src/assets/img/zone/';
    $imgPath = '../assets/img/zone/';
    // ฟังก์ชันสำหรับการจัดการการอัปโหลดไฟล์
    function handleUpload($fileKey, $imgPath) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $imgName = uniqid() . '.png'; // สมมติว่าเป็นไฟล์ PNG เพื่อความง่าย
            $targetFilePath = $imgPath . $imgName;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetFilePath)) {
                return $imgName;
            } else {
                return null; // หากการย้ายไฟล์ล้มเหลว
            }
        } else {
            return null; // หากไม่มีการอัปโหลดรูปภาพ
        }
    }
    // จัดการการอัปโหลดไฟล์ img_showzone
    $imgShowzone = handleUpload('img_showzone', $imgPath);

    // จัดการการอัปโหลดไฟล์ img_mapzone
    $imgMapzone = handleUpload('img_mapzone', $imgPath);

    // เตรียม SQL statement
    $sql = "UPDATE zone SET name_zone = ?, img_showzone = IFNULL(?, img_showzone), img_mapzone = IFNULL(?, img_mapzone), detail_zone = ? WHERE id_zone = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssi', $_POST['name_zone'], $imgShowzone, $imgMapzone, $_POST['detail_zone'], $_POST['id_zone']);
    $stmt->execute();

    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        $data = ["affected_rows" => $affected];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); // ส่งกลับสถานะ 200 OK
    } else {
        // หากการอัปเดตข้อมูลล้มเหลว
        $errorResponse = ["message" => "Failed to update zone"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // ส่งกลับสถานะ 500 Internal Server Error
    }
});

$app->post('/add-zone', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // กำหนดไดเรกทอรีสำหรับการอัปโหลดรูปภาพ
    // $imgPath = 'C:/Users/aleny/Desktop/Final/New/my-project/my-project/src/assets/img/zone/';
    $imgPath = '../assets/img/zone/';
    // ฟังก์ชันสำหรับการจัดการการอัปโหลดไฟล์
    function handleUpload($fileKey, $imgPath) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $imgName = uniqid() . '.png'; // สมมติว่าเป็นไฟล์ PNG เพื่อความง่าย
            $targetFilePath = $imgPath . $imgName;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetFilePath)) {
                return $imgName;
            } else {
                return null; // หากการย้ายไฟล์ล้มเหลว
            }
        } else {
            return null; // หากไม่มีการอัปโหลดรูปภาพ
        }
    }

    // จัดการการอัปโหลดไฟล์ img_showzone
    $imgShowzone = handleUpload('img_showzone', $imgPath);

    // จัดการการอัปโหลดไฟล์ img_mapzone
    $imgMapzone = handleUpload('img_mapzone', $imgPath);

    // เตรียมและดำเนินการ SQL statement
    $sql = "INSERT INTO zone (name_zone, img_showzone, img_mapzone, detail_zone) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $_POST['name_zone'], $imgShowzone, $imgMapzone, $_POST['detail_zone']);
    $stmt->execute();

    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        $data = ["affected_rows" => $affected, "last_idx" => $conn->insert_id];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); // ส่งกลับสถานะ 200 OK
    } else {
        // หากการแทรกข้อมูลล้มเหลว
        $errorResponse = ["message" => "Failed to create zone"];
        $response->getBody()->write(json_encode($errorResponse));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500); // ส่งกลับสถานะ 500 Internal Server Error
    }
});

$app->delete('/delete-zone/{id_zone}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];

    // รับ ID จากพารามิเตอร์ของเส้นทาง
    $zoneId = $args['id_zone'];

    // เริ่มต้นการทำธุรกรรม (transaction)
    $conn->begin_transaction();

    try {
        // ลบข้อมูลจากตาราง reservation ที่เชื่อมโยงกับตาราง boot ผ่าน id_reservation ที่มี id_zone ตรงกัน
        $sqlReservation = "DELETE FROM reservation WHERE id_reservation IN (SELECT id_reservation FROM boot WHERE id_zone = ?)";
        $stmtReservation = $conn->prepare($sqlReservation);
        $stmtReservation->bind_param('i', $zoneId);
        $stmtReservation->execute();

        // ลบข้อมูลจากตารางอื่น ๆ ที่เชื่อมโยงกับ reservation ที่มี id_reservation ตรงกับ id_boot ที่มี id_zone ตรงกัน
        // ใส่โค้ดเพิ่มเติมที่นี่เพื่อลบข้อมูลในตารางอื่น ๆ ที่เชื่อมโยงกับ reservation

        // ลบข้อมูลจากตาราง boot ที่มี id_zone ตรงกัน
        $sqlBoot = "DELETE FROM boot WHERE id_zone = ?";
        $stmtBoot = $conn->prepare($sqlBoot);
        $stmtBoot->bind_param('i', $zoneId);
        $stmtBoot->execute();

        // ลบข้อมูลจากตาราง zone
        $sqlZone = "DELETE FROM zone WHERE id_zone = ?";
        $stmtZone = $conn->prepare($sqlZone);
        $stmtZone->bind_param('i', $zoneId);
        $stmtZone->execute();
        
        // ตรวจสอบจำนวนแถวที่ได้รับผลกระทบ
        $affectedZone = $stmtZone->affected_rows;

        if ($affectedZone > 0) {
            // ยืนยันการทำธุรกรรม (commit transaction)
            $conn->commit();

            $data = [
                "message" => "Zone and related boots and reservations deleted successfully",
                "affected_zone_rows" => $affectedZone
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


// $app->get('/boot-test', function (Request $request, Response $response) {
//     $conn = $GLOBALS['conn'];
//     $sql = 'SELECT * FROM boot'; // เปลี่ยนเป็นการเลือกข้อมูลจากตาราง boot
//     $stmt = $conn->prepare($sql);
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
$app->get('/boot-test/{id_boot}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['conn'];
    $id_boot = $args['id_boot'];

    $sql = 'SELECT * FROM boot WHERE id_boot = ?'; // เลือกข้อมูลจากตาราง boot โดยระบุ id_boot
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_boot); // กำหนดประเภทของ parameter เป็น integer
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


$app->get('/zone', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];
    $sql = 'SELECT z.*, 
                   COUNT(b.id_boot) AS boot_count,
                   SUM(CASE WHEN b.status_boot = "ว่าง" THEN 1 ELSE 0 END) AS empty_boot_count,
                    SUM(CASE WHEN b.status_boot = "จองสําเร็จ" THEN 1 ELSE 0 END) AS booked_boot_count,
                    SUM(CASE WHEN b.status_boot = "อยู่ระหว่างตรวจสอบ" THEN 1 ELSE 0 END) AS wait_boot_count
            FROM zone z
            LEFT JOIN boot b ON z.id_zone = b.id_zone
            GROUP BY z.id_zone';
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

$app->get('/zone/{id_zone}', function (Request $request, Response $response, array $args) {
    $conn = $GLOBALS['conn'];
    $id_zone = $args['id_zone'];

    $sql = 'SELECT z.*, COUNT(b.id_boot) AS boot_count
            FROM zone z
            LEFT JOIN boot b ON z.id_zone = b.id_zone
            WHERE z.id_zone = ?
            GROUP BY z.id_zone';
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_zone);
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


// $app->get('/boot-reservation/{id_boot}', function (Request $request, Response $response, array $args) {
//     $conn = $GLOBALS['conn'];
//     $id_boot = explode(',', $args['id_boot']); // แยก id_boot ที่รับมาด้วยเครื่องหมาย ','
  
//     $placeholders = rtrim(str_repeat('?,', count($id_boot)), ','); // สร้าง placeholders สำหรับการ bind parameters
//     $types = str_repeat('i', count($id_boot)); // กำหนดประเภทของ parameters เป็น integer
  
//     $sql = 'SELECT boot.*, zone.id_zone 
//         FROM boot
//         INNER JOIN zone ON boot.id_zone = zone.id_zone
//         WHERE boot.id_boot IN (' . $placeholders . ')'; // ใช้ IN clause เพื่อเลือกข้อมูลจากหลาย id_boot
//     $stmt = $conn->prepare($sql);
  
//     // ใช้ call_user_func_array เพื่อ bind parameters แบบ dynamic
//     $params = array_merge(array($types), $id_boot);
//     call_user_func_array(array($stmt, 'bind_param'), $params);
  
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
//   });
  
$app->get('/boot/{id_zone}', function (Request $request, Response $response, array $args) {
    $conn = $GLOBALS['conn'];
    $id_zone = $args['id_zone'];
    
    $sql = 'SELECT boot.*, zone.id_zone 
        FROM boot
        INNER JOIN zone ON boot.id_zone = zone.id_zone
        WHERE zone.id_zone = ?
        ORDER BY CAST(boot.number_boot AS UNSIGNED) ASC';// เรียงลำดับตัวเลขจากน้อยไปมาก
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_zone);
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

// $app->get('/boot/{id_zone}', function (Request $request, Response $response, array $args) {
//     $conn = $GLOBALS['conn'];
//     $id_zone = $args['id_zone'];
    
//     $sql = 'SELECT boot.*, zone.id_zone 
//             FROM boot
//             INNER JOIN zone ON boot.id_zone = zone.id_zone
//             WHERE zone.id_zone = ?';
            
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("i", $id_zone);
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

$app->get('/boot', function (Request $request, Response $response) {
    $conn = $GLOBALS['conn'];
    $sql = 'SELECT boot.*, zone.id_zone                                                                                  
            FROM boot
            INNER JOIN zone ON boot.id_zone = zone.id_zone';
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
