<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Shell Interface</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .database-list, .table-list {
            margin-top: 20px;
        }
        .database-item, .table-item {
            background-color: #f9f9f9;
            padding: 10px;
            margin-bottom: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .error {
            color: red;
            padding: 10px;
            background-color: #ffe6e6;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .success {
            color: green;
            padding: 10px;
            background-color: #e6ffe6;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Shell Interface</h1>
        
        <?php
        session_start();
        $conn = null;
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $host = $_POST['host'] ?? 'localhost';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            try {
                $conn = new PDO("mysql:host=$host", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $_SESSION['db_credentials'] = [
                    'host' => $host,
                    'username' => $username,
                    'password' => $password
                ];
                $success = "Successfully connected to database server!";
            } catch(PDOException $e) {
                $error = "Connection failed: " . $e->getMessage();
            }
        }

        // Restore connection if session exists
        if (!$conn && isset($_SESSION['db_credentials'])) {
            try {
                $conn = new PDO(
                    "mysql:host=" . $_SESSION['db_credentials']['host'],
                    $_SESSION['db_credentials']['username'],
                    $_SESSION['db_credentials']['password']
                );
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch(PDOException $e) {
                $error = "Session connection failed: " . $e->getMessage();
                unset($_SESSION['db_credentials']);
            }
        }
        ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!$conn): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="host">Host:</label>
                    <input type="text" id="host" name="host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password">
                </div>
                <button type="submit">Connect</button>
            </form>
        <?php else: ?>
            <?php
            // Get all databases
            $databases = $conn->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
            ?>
            
            <div class="database-list">
                <h2>Available Databases:</h2>
                <?php foreach ($databases as $database): ?>
                    <?php if ($database != 'information_schema' && $database != 'performance_schema' && $database != 'mysql' && $database != 'sys'): ?>
                        <div class="database-item">
                            <h3><?php echo htmlspecialchars($database); ?></h3>
                            <?php
                            // Get tables for this database
                            $tables = $conn->query("SHOW TABLES FROM `" . $database . "`")->fetchAll(PDO::FETCH_COLUMN);
                            if (count($tables) > 0):
                            ?>
                                <div class="table-list">
                                    <h4>Tables:</h4>
                                    <?php foreach ($tables as $table): ?>
                                        <div class="table-item">
                                            <?php echo htmlspecialchars($table); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p>No tables found in this database.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="disconnect" value="1">
                <button type="submit" style="background-color: #f44336;">Disconnect</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html> 