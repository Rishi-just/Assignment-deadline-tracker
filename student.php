<?php
include "db.php";


if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "student") {
    $_SESSION["login_message"] = "Please login first.";
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$student_id = (int) $_SESSION["user_id"];
$message = $_SESSION["student_message"] ?? "";
$message_class = $_SESSION["student_message_class"] ?? "message";

unset($_SESSION["student_message"], $_SESSION["student_message_class"]);

function remove_file_if_exists($file_path)
{
    if ($file_path != "" && file_exists($file_path)) {
        unlink($file_path);
    }
}

function upload_student_pdf($file, $student_id, $assignment_id, $current_file)
{
    if (!isset($file) || $file["error"] == UPLOAD_ERR_NO_FILE) {
        return [
            "error" => "",
            "path" => $current_file,
            "uploaded" => false
        ];
    }

    if ($file["error"] != UPLOAD_ERR_OK) {
        return [
            "error" => "The PDF upload failed. Please try again.",
            "path" => $current_file,
            "uploaded" => false
        ];
    }

    $file_ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    if ($file_ext != "pdf") {
        return [
            "error" => "Only PDF files are allowed for submissions.",
            "path" => $current_file,
            "uploaded" => false
        ];
    }

    $target_dir = "uploads/submissions";

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $new_name = $target_dir . "/" . time() . "_" . $student_id . "_" . $assignment_id . ".pdf";

    if (!move_uploaded_file($file["tmp_name"], $new_name)) {
        return [
            "error" => "The PDF upload failed. Please try again.",
            "path" => $current_file,
            "uploaded" => false
        ];
    }

    if ($current_file != "" && $current_file != $new_name) {
        remove_file_if_exists($current_file);
    }

    return [
        "error" => "",
        "path" => $new_name,
        "uploaded" => true
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $assignment_id = (int) ($_POST["assignment_id"] ?? 0);
    $status = $_POST["status"] ?? "pending";

    if ($status != "pending" && $status != "completed") {
        $status = "pending";
    }

    $assignment_check = mysqli_query($conn, "SELECT id, deadline FROM assignments WHERE id = $assignment_id");

    if (!$assignment_check || mysqli_num_rows($assignment_check) == 0) {
        $_SESSION["student_message"] = "Assignment not found.";
        $_SESSION["student_message_class"] = "message error-message";
        echo "<script>window.location.href='student.php';</script>";
        exit;
    }

    $assignment_row = mysqli_fetch_assoc($assignment_check);

    $check = mysqli_query($conn, "SELECT id, submission_pdf, submitted_at FROM student_tasks WHERE student_id = $student_id AND assignment_id = $assignment_id");
    $existing_task = mysqli_num_rows($check) > 0 ? mysqli_fetch_assoc($check) : null;
    $current_pdf = $existing_task["submission_pdf"] ?? "";
    $deadline_passed = strtotime($assignment_row["deadline"]) < time();

    if ($current_pdf != "") {
        $_SESSION["student_message"] = "You have already submitted this assignment. You can submit it only one time.";
        $_SESSION["student_message_class"] = "message error-message";
        echo "<script>window.location.href='student.php';</script>";
        exit;
    }

    if ($deadline_passed) {
        $_SESSION["student_message"] = "Deadline has passed. You cannot submit this assignment now.";
        $_SESSION["student_message_class"] = "message error-message";
        echo "<script>window.location.href='student.php';</script>";
        exit;
    }

    if (!isset($_FILES["submission_pdf"]) || $_FILES["submission_pdf"]["error"] == UPLOAD_ERR_NO_FILE) {
        $_SESSION["student_message"] = "Please upload your completed assignment PDF before submitting.";
        $_SESSION["student_message_class"] = "message error-message";
        echo "<script>window.location.href='student.php';</script>";
        exit;
    }

    $upload_result = upload_student_pdf($_FILES["submission_pdf"] ?? null, $student_id, $assignment_id, $current_pdf);

    if ($upload_result["error"] != "") {
        $_SESSION["student_message"] = $upload_result["error"];
        $_SESSION["student_message_class"] = "message error-message";
        echo "<script>window.location.href='student.php';</script>";
        exit;
    }

    $submission_pdf = $upload_result["path"];

    if ($upload_result["uploaded"] || $submission_pdf != "") {
        $status = "completed";
    }

    $escaped_status = mysqli_real_escape_string($conn, $status);
    $submission_sql = $submission_pdf == ""
        ? "NULL"
        : "'" . mysqli_real_escape_string($conn, $submission_pdf) . "'";

    if ($submission_pdf == "") {
        $submitted_at_sql = "NULL";
    } elseif ($upload_result["uploaded"]) {
        $submitted_at_sql = "NOW()";
    } elseif (!empty($existing_task["submitted_at"])) {
        $submitted_at_sql = "'" . mysqli_real_escape_string($conn, $existing_task["submitted_at"]) . "'";
    } else {
        $submitted_at_sql = "NOW()";
    }

    if ($existing_task) {
        mysqli_query(
            $conn,
            "UPDATE student_tasks
             SET status = '$escaped_status',
                 submission_pdf = $submission_sql,
                 submitted_at = $submitted_at_sql
             WHERE student_id = $student_id AND assignment_id = $assignment_id"
        );
    } else {
        mysqli_query(
            $conn,
            "INSERT INTO student_tasks (student_id, assignment_id, status, submission_pdf, submitted_at)
             VALUES ($student_id, $assignment_id, '$escaped_status', $submission_sql, $submitted_at_sql)"
        );
    }

    $_SESSION["student_message"] = $upload_result["uploaded"]
        ? "Submission uploaded successfully and marked as completed."
        : "Assignment status updated successfully.";
    $_SESSION["student_message_class"] = "message success-message";
    echo "<script>window.location.href='student.php';</script>";
    exit;
}

$assignments = mysqli_query($conn, "SELECT assignments.*, users.name AS teacher_name,
                                   COALESCE(student_tasks.status, 'pending') AS student_status,
                                   student_tasks.submission_pdf,
                                   student_tasks.submitted_at
                                   FROM assignments
                                   JOIN users ON assignments.teacher_id = users.id
                                   LEFT JOIN student_tasks ON student_tasks.assignment_id = assignments.id
                                                          AND student_tasks.student_id = $student_id
                                   ORDER BY assignments.deadline ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h2>Student Dashboard</h2>
        <div>
            <span>Hello, <?php echo $_SESSION["name"]; ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="items">
        <div class="box bigbox">
            <h1>All Assignments</h1>

            <p class="muted-text">Upload your completed assignment PDF from the card below. When a PDF is uploaded, the assignment is marked as completed automatically.</p>

            <?php if ($message != "") { ?>
                <div class="<?php echo htmlspecialchars($message_class); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php } ?>

            <?php if (mysqli_num_rows($assignments) == 0) { ?>
                <p>No assignments available.</p>
            <?php } ?>

            <?php while ($row = mysqli_fetch_assoc($assignments)) { ?>
                <?php
                $status = $row["student_status"];
                $submission_pdf = $row["submission_pdf"] ?? "";
                $submitted_at = $row["submitted_at"] ?? "";
                $deadline_passed = strtotime($row["deadline"]) < time();
                $already_submitted = $submission_pdf != "";
                $can_submit = !$deadline_passed && !$already_submitted;

                $deadline_class = "normal";
                if ($deadline_passed) {
                    $deadline_class = "late";
                }

                if ($status == "completed") {
                    $deadline_class = "done";
                }
                ?>

                <div class="card <?php echo $deadline_class; ?>">
                    <h3><?php echo htmlspecialchars($row["title"]); ?></h3>
                    <p><b>Subject:</b> <?php echo htmlspecialchars($row["subject"]); ?></p>
                    <p><b>Teacher:</b> <?php echo htmlspecialchars($row["teacher_name"]); ?></p>
                    <p><b>Deadline:</b> <?php echo date("d M Y h:i A", strtotime($row["deadline"])); ?></p>
                    <p><b>Your Status:</b> <?php echo htmlspecialchars(ucfirst($status)); ?></p>

                    <?php if ($submission_pdf != "") { ?>
                        <p><b>Your PDF:</b> <a href="<?php echo htmlspecialchars($submission_pdf); ?>" target="_blank">Open Submitted PDF</a></p>
                        <p><b>Submitted On:</b> <?php echo date("d M Y h:i A", strtotime($submitted_at)); ?></p>
                    <?php } else { ?>
                        <p class="muted-text">No submission PDF uploaded yet.</p>
                    <?php } ?>

                    <?php if ($can_submit) { ?>
                        <form method="POST" enctype="multipart/form-data" class="form2">
                            <input type="hidden" name="assignment_id" value="<?php echo $row["id"]; ?>">
                            <select name="status">
                                <option value="pending" <?php if ($status == "pending") { echo "selected"; } ?>>Pending</option>
                                <option value="completed" <?php if ($status == "completed") { echo "selected"; } ?>>Completed</option>
                            </select>
                            <label class="inline-label">Upload Completed PDF</label>
                            <input type="file" name="submission_pdf" accept=".pdf,application/pdf" required>
                            <button type="submit">Submit Assignment</button>
                        </form>
                    <?php } elseif ($already_submitted) { ?>
                        <div class="message success-message">
                            Assignment already submitted. Submit button is hidden because only one submission is allowed.
                        </div>
                    <?php } else { ?>
                        <div class="message error-message">
                            Submission closed. Deadline has already passed for this assignment.
                        </div>
                    <?php } ?>

                    <a class="click" href="view.php?id=<?php echo $row["id"]; ?>">View Details</a>
                </div>
            <?php } ?>
        </div>
    </div>
</body>
</html>
