<?php
// Start the session This must be the very first thing in the script.
// It either starts a new user session or resumes an existing one using the cookie.
session_start();
$message = '';

// Check if the 'loggedin' variable exists in the session and is set to true.
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

// LOGIN LOGIC (Checks if the user submitted the login form) ---
if (isset($_POST['login'])) {
    // Hardcoded credentials for this example. 
    // In real life, these would be checked against a secure database.
    $username = 'admin'; 
    $password = 'password123'; 

    $input_username = $_POST['username'];
    $input_password = $_POST['password'];

    // Simple check: If username and password match, set the session.
    if ($input_username === $username && $input_password === $password) {
        $_SESSION['loggedin'] = true; // Set the 'logged in' flag in the server's storage
        $_SESSION['username'] = $username;
        $is_logged_in = true; // Update local variable so the page changes immediately
    } else {
        $message = 'Invalid username or password.';
    }
}

//(Checks if the user clicked the logout link)
if (isset($_GET['logout'])) {
    // 1. Clear the server's storage:
    $_SESSION = array(); 
    
    // 2. Clear the browser's key (cookie):
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // 3. Destroy the session file on the server:
    session_destroy(); 
    
    // Redirect to the main page to show the clean login form:
    header('Location: bookSystem.php'); 
    exit();
}

if ($is_logged_in) {
    //DATABASE CONNECTION
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'bookdb';

    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    //VARIABLES
    $title = $author = $year = $price = '';
    $update = false; // Flag to check if we are in 'Edit' mode
    $book_id = 0;
    
    //3. CREATE OPERATION (Add New Book)
    if (isset($_POST['save'])) {
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $year = trim($_POST['year']);
        $price = trim($_POST['price']);

        if ($title == '' || $author == '' || $year == '' || $price == '') {
            $message = 'All fields are required!';
        } else {
            // Prepared statement: Safer way to run queries to prevent SQL Injection
            $stmt = $conn->prepare('INSERT INTO books (title, author, year, price) VALUES (?, ?, ?, ?)');
            // 'ssii' means bind 2 strings, 2 integers to the question marks (?)
            $stmt->bind_param('ssii', $title, $author, $year, $price);
            if ($stmt->execute()) {
                $message = ' Book added successfully!';
            } else {
                $message = ' Error: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    // 4. DELETE OPERATION
    if (isset($_GET['delete'])) {
        $book_id = $_GET['delete'];
        $stmt = $conn->prepare('DELETE FROM books WHERE book_id = ?');
        // 'i' means bind 1 integer (the book ID) to the question mark (?)
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        $stmt->close();
        $message = 'Book deleted!';
    }

    //5. SELECT OPERATION (Load data for Editing) 
    if (isset($_GET['edit'])) {
        $book_id = $_GET['edit'];
        $stmt = $conn->prepare('SELECT * FROM books WHERE book_id = ?');
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        $result_edit = $stmt->get_result();
        
        // If we found exactly one row, load its data into the form variables
        if ($result_edit->num_rows == 1) {
            $row = $result_edit->fetch_assoc();
            $title = $row['title'];
            $author = $row['author'];
            $year = $row['year'];
            $price = $row['price'];
            $update = true; // Set the flag to display the 'Update' button in the HTML
        }
        $stmt->close();
    }

    //  6. UPDATE OPERATION 
    if (isset($_POST['update'])) {
        $book_id = $_POST['book_id'];
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $year = trim($_POST['year']);
        $price = trim($_POST['price']);

        if ($title == '' || $author == '' || $year == '' || $price == '') {
            $message = 'All fields are required!';
        } else {
            // Prepared statement to change all fields for a specific book ID
            // 'ssiii' means 2 strings, 3 integers (the last integer is the book_id)
            $stmt = $conn->prepare('UPDATE books SET title=?, author=?, year=?, price=? WHERE book_id=?');
            $stmt->bind_param('ssiii', $title, $author, $year, $price, $book_id);
            if ($stmt->execute()) {
                $message = 'Book updated successfully!';
            } else {
                $message = 'Error: ' . $stmt->error;
            }
            $stmt->close();
            // Reset variables and update flag after a successful update
            $update = false; 
            $title = $author = $year = $price = '';
        }
    }

    // --- 7. SELECT ALL OPERATION (Load data for the main table) ---
    $result = $conn->query('SELECT * FROM books');

} // END if ($is_logged_in)
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Book System</title>
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f6f8;
        color: #333;
        margin: 0;
        padding: 30px 20px;
    }
    h2, h3 {
        text-align: center;
        color: #85afd9ff; 
        margin-bottom: 25px;
    }
    .container, .login-container {
        width: 90%;
        max-width: 600px; 
        margin: 0 auto;
        background: #fff;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .login-container {
        max-width: 400px;
        margin: 50px auto;
    }
    
    form {
        display: flex;
        flex-direction: column;
        gap: 15px; 
        margin-bottom: 30px;
    }
    label {
        font-weight: bold;
        color: #555;
        margin-bottom: -5px; 
    }
    input[type=text], input[type=number], input[type=password] {
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
        width: 100%;
        box-sizing: border-box; 
        transition: border-color 0.3s;
    }
    input:focus {
        border-color: #85afd9ff;
        outline: none;
    }
    button {
        background-color: #85afd9ff;
        color: white;
        padding: 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        transition: background-color 0.3s;
        margin-top: 10px; 
    }
    button:hover {
        background-color: #0056b3;
    }
    
    a.button {
        text-decoration: none;
        color: #fff;
        background: #6c757d;
        padding: 6px 10px;
        border-radius: 4px;
        font-size: 0.9em;
        display: inline-block; /* Essential for alignment */
        transition: background-color 0.3s;
    }
    a.button:hover {
        background: #5a6268;
    }
    
    .logout-bar {
        display: flex;
        justify-content: flex-end; 
        align-items: center;
        margin-bottom: 15px;
        margin-top: -10px; 
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    .logout-bar span {
        font-weight: 600;
        color: #555;
        margin-right: 15px;
        font-size: 0.9em;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    th, td {
        border: 1px solid #e0e0e0;
        padding: 12px 8px; 
        text-align: center;
    }
    th {
        background-color: #85afd9ff;
        color: white;
        font-weight: bold;
    }
    td {
        background-color: #fefefe;
    }
    tr:nth-child(even) td {
        background-color: #f9f9f9;
    }
    
    /* Message Styling */
    .message {
        text-align: center;
        margin: 15px 0;
        padding: 10px;
        font-weight: bold;
        border-radius: 5px;
        background-color: #e6f7ff; 
        color: #333;
    }
</style>
</head>
<body>

<?php 
// If the user is NOT logged in, show the login form.
if (!$is_logged_in): 
?>

<div class="login-container">
    <h2>Admin Login</h2>
    <?php if ($message != '') echo '<p class="message" style="color:red;">' . htmlspecialchars($message) . '</p>'; ?>
    <form method="post" action="bookSystem.php">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        
        <button type="submit" name="login">Log In</button>
    </form>
</div>

<?php 
// If the user IS logged in, show the book management system.
else: 
?>

<div class="container">
    <h2>📚 Book Management System</h2>
    <div class="logout-bar">
        <span style="margin-right: 15px;">Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="?logout=true" class="button" style="background-color: #dc3545;">Logout</a>
    </div>

    <?php if ($message != '') echo '<p class="message">' . htmlspecialchars($message) . '</p>'; ?>

    <form method="post" action="bookSystem.php">
        <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
        <label>Title:</label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($title); ?>">

        <label>Author:</label>
        <input type="text" name="author" value="<?php echo htmlspecialchars($author); ?>">

        <label>Year:</label>
        <input type="number" name="year" value="<?php echo htmlspecialchars($year); ?>">

        <label>Price:</label>
        <input type="number" name="price" value="<?php echo htmlspecialchars($price); ?>">

        <?php if ($update): ?>
            <button type="submit" name="update">Update Book</button>
            <a href="bookSystem.php" class="button">Cancel</a>
        <?php else: ?>
            <button type="submit" name="save">Add Book</button>
        <?php endif; ?>
    </form>

    <h3>Book List</h3>
    <table>
        <tr>
            <th>Book ID</th>
            <th>Title</th>
            <th>Author</th>
            <th>Year</th>
            <th>Price</th>
            <th>Actions</th>
        </tr>
        <?php 
        // Loop through all results from the 'SELECT ALL' query 
        while ($row = $result->fetch_assoc()): 
        ?>
            <tr>
                <td><?php echo $row['book_id']; ?></td>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['author']); ?></td>
                <td><?php echo htmlspecialchars($row['year']); ?></td>
                <td><?php echo htmlspecialchars($row['price']); ?></td>
                <td>
                    <a href="?edit=<?php echo $row['book_id']; ?>" class="button">Edit</a>
                    <a href="?delete=<?php echo $row['book_id']; ?>" class="button" style="background:#dc3545;" onclick="return confirm('Are you sure you want to delete this book?');">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

<?php 
// Close the database connection after all operations are complete
$conn->close(); 
endif; // End of the 'if ($is_logged_in)' block
?>

</body>
</html>