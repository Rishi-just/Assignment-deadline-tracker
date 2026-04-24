<?php
include "db.php";


if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teacher") {
    $_SESSION["login_message"] = "Please login first.";
    echo "<script>window.location.href='login.php';</script>";
    exit;
}


$teacher_id = $_SESSION["user_id"];
$message = $_SESSION["teacher_message"] ?? "";
$message_class = $_SESSION["teacher_message_class"] ?? "message";
$form_values = $_SESSION["teacher_form_values"] ?? [
    "title" => "",
    "subject" => "",
    "description" => "",
    "deadline" => ""
];

unset($_SESSION["teacher_message"], $_SESSION["teacher_message_class"], $_SESSION["teacher_form_values"]);

function remove_file_if_exists($file_path)
{
    if ($file_path != "" && file_exists($file_path)) {
        unlink($file_path);
    }
}

function upload_assignment_pdf($file, $teacher_id)
{
    if (!isset($file) || $file["error"] == UPLOAD_ERR_NO_FILE) {
        return [
            "error" => "",
            "path" => ""
        ];
    }

    if ($file["error"] != UPLOAD_ERR_OK) {
        return [
            "error" => "PDF upload failed.",
            "path" => ""
        ];
    }

    $file_ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    if ($file_ext != "pdf") {
        return [
            "error" => "Only PDF files are allowed.",
            "path" => ""
        ];
    }

    $target_dir = "uploads/assignments";

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $new_name = $target_dir . "/" . time() . "_" . $teacher_id . ".pdf";

    if (!move_uploaded_file($file["tmp_name"], $new_name)) {
        return [
            "error" => "PDF upload failed.",
            "path" => ""
        ];
    }

    return [
        "error" => "",
        "path" => $new_name
    ];
}

function delete_assignment_record($conn, $assignment_id, $teacher_id)
{
    $assignment_result = mysqli_query($conn, "SELECT pdf_file FROM assignments WHERE id = $assignment_id AND teacher_id = $teacher_id");

    if (!$assignment_result || mysqli_num_rows($assignment_result) == 0) {
        return false;
    }

    $assignment = mysqli_fetch_assoc($assignment_result);
    remove_file_if_exists($assignment["pdf_file"] ?? "");

    $submission_result = mysqli_query($conn, "SELECT submission_pdf FROM student_tasks WHERE assignment_id = $assignment_id");

    if ($submission_result) {
        while ($submission = mysqli_fetch_assoc($submission_result)) {
            remove_file_if_exists($submission["submission_pdf"] ?? "");
        }
    }

    mysqli_query($conn, "DELETE FROM assignments WHERE id = $assignment_id AND teacher_id = $teacher_id");
    return true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_confirm"])) {
    $delete_id = (int) $_POST["delete_id"];
    delete_assignment_record($conn, $delete_id, $teacher_id);
    $_SESSION["teacher_message"] = "Assignment deleted successfully.";
    $_SESSION["teacher_message_class"] = "message success-message";
    header("Location: teacher.php");
    exit;
}

if (isset($_GET["delete"])) {
    $delete_id = (int) $_GET["delete"];
    delete_assignment_record($conn, $delete_id, $teacher_id);
    header("Location: teacher.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title_input = trim($_POST["title"] ?? "");
    $subject_input = trim($_POST["subject"] ?? "");
    $description_input = trim($_POST["description"] ?? "");
    $deadline_input = $_POST["deadline"] ?? "";
    $title = mysqli_real_escape_string($conn, $title_input);
    $subject = mysqli_real_escape_string($conn, $subject_input);
    $description = mysqli_real_escape_string($conn, $description_input);
    $deadline = mysqli_real_escape_string($conn, str_replace("T", " ", $deadline_input));
    $pdf_file = "";

    $_SESSION["teacher_form_values"] = [
        "title" => $title_input,
        "subject" => $subject_input,
        "description" => $description_input,
        "deadline" => $deadline_input
    ];

    if ($title == "" || $subject == "" || $description == "" || $deadline == "") {
        $_SESSION["teacher_message"] = "Please fill in all assignment fields.";
        $_SESSION["teacher_message_class"] = "message error-message";
        header("Location: teacher.php");
        exit;
    } elseif (strtotime($deadline) < time()) {
        $_SESSION["teacher_message"] = "Past deadline is not allowed. Please choose a future date and time.";
        $_SESSION["teacher_message_class"] = "message error-message";
        header("Location: teacher.php");
        exit;
    } else {
        $upload_result = upload_assignment_pdf($_FILES["pdf_file"] ?? null, $teacher_id);

        if ($upload_result["error"] != "") {
            $_SESSION["teacher_message"] = $upload_result["error"];
            $_SESSION["teacher_message_class"] = "message error-message";
            header("Location: teacher.php");
            exit;
        } else {
            $pdf_file = $upload_result["path"];

            $sql = "INSERT INTO assignments (title, subject, description, deadline, pdf_file, teacher_id)
                    VALUES ('$title', '$subject', '$description', '$deadline', '$pdf_file', $teacher_id)";
            mysqli_query($conn, $sql);
            unset($_SESSION["teacher_form_values"]);
            $_SESSION["teacher_message"] = "Assignment added successfully.";
            $_SESSION["teacher_message_class"] = "message success-message";
            header("Location: teacher.php");
            exit;
        }
    }
}

$assignments = mysqli_query($conn, "SELECT assignments.*,
                                   (SELECT COUNT(*) FROM student_tasks WHERE assignment_id = assignments.id AND status = 'completed') AS completed_count,
                                   (SELECT COUNT(*) FROM student_tasks WHERE assignment_id = assignments.id AND submission_pdf IS NOT NULL AND submission_pdf != '') AS submitted_pdf_count
                                   FROM assignments
                                   WHERE teacher_id = $teacher_id
                                   ORDER BY deadline ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h2>Teacher Dashboard</h2>
        <div>
            <span>Hello, <?php echo $_SESSION["name"]; ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="items">
        <div class="box">
            <h1>Add Assignment</h1>

            <?php if ($message != "") { ?>
                <div class="<?php echo htmlspecialchars($message_class); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php } ?>

            <form method="POST" enctype="multipart/form-data">
                <label>Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($form_values["title"]); ?>" required>

                <label>Subject</label>
                <input type="text" name="subject" value="<?php echo htmlspecialchars($form_values["subject"]); ?>" required>

                <label>Description</label>
                <textarea name="description" rows="4" required><?php echo htmlspecialchars($form_values["description"]); ?></textarea>

                <label>Deadline</label>
                <input type="datetime-local" name="deadline" value="<?php echo htmlspecialchars($form_values["deadline"]); ?>" required>

                <label>Upload PDF</label>
                <input type="file" name="pdf_file" accept=".pdf,application/pdf">

                <button type="submit">Add Assignment</button>
            </form>
        </div>

        <div class="box">
            <h1>My Assignments</h1>

            <?php if (mysqli_num_rows($assignments) == 0) { ?>
                <p>No assignments added yet.</p>
            <?php } ?>

            <?php while ($row = mysqli_fetch_assoc($assignments)) { ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($row["title"]); ?></h3>
                    <p><b>Subject:</b> <?php echo htmlspecialchars($row["subject"]); ?></p>
                    <p><b>Deadline:</b> <?php echo date("d M Y h:i A", strtotime($row["deadline"])); ?></p>
                    <p><b>Completed Students:</b> <?php echo (int) $row["completed_count"]; ?></p>
                    <p><b>Submitted PDFs:</b> <?php echo (int) $row["submitted_pdf_count"]; ?></p>
                    <?php if ($row["pdf_file"] != "") { ?>
                        <p><a href="<?php echo htmlspecialchars($row["pdf_file"]); ?>" target="_blank">Open PDF</a></p>
                    <?php } ?>
                    <a class="click" href="view.php?id=<?php echo $row["id"]; ?>">View</a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="delete_id" value="<?php echo $row["id"]; ?>">
                        <button type="submit" name="delete_confirm" class="delete">Delete</button>
                    </form>
                </div>
            <?php } ?>
        </div>
    </div>
</body>
</html>
