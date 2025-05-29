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
        .dump-button {
            background-color: #2196F3;
            margin-left: 10px;
        }
        .dump-button:hover {
            background-color: #1976D2;
        }
        .table-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .table-content {
            margin-top: 10px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
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

        // Function to generate database dump
        function generateDump($conn, $database) {
            $tables = $conn->query("SHOW TABLES FROM `" . $database . "`")->fetchAll(PDO::FETCH_COLUMN);
            $dump = "-- Database Dump for {$database}\n";
            $dump .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($tables as $table) {
                // Get create table statement
                $createTable = $conn->query("SHOW CREATE TABLE `{$database}`.`{$table}`")->fetch(PDO::FETCH_ASSOC);
                $dump .= "\n-- Table structure for `{$table}`\n";
                $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $dump .= $createTable['Create Table'] . ";\n\n";
                
                // Get table data
                $rows = $conn->query("SELECT * FROM `{$database}`.`{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                if (count($rows) > 0) {
                    $dump .= "-- Data for table `{$table}`\n";
                    foreach ($rows as $row) {
                        $values = array_map(function($value) use ($conn) {
                            if ($value === null) return 'NULL';
                            return $conn->quote($value);
                        }, $row);
                        $dump .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $dump .= "\n";
                }
            }
            return $dump;
        }

        // Handle database dump download
        if (isset($_POST['dump_database']) && isset($_POST['database'])) {
            if ($conn) {
                $database = $_POST['database'];
                $dump = generateDump($conn, $database);
                
                // Set headers for file download
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $database . '_dump_' . date('Y-m-d_H-i-s') . '.sql"');
                header('Content-Length: ' . strlen($dump));
                echo $dump;
                exit;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['disconnect'])) {
                session_destroy();
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
            
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
                            <div class="table-actions">
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="database" value="<?php echo htmlspecialchars($database); ?>">
                                    <button type="submit" name="dump_database" class="dump-button">Export Database Dump</button>
                                </form>
                            </div>
                            <?php
                            // Get tables for this database
                            $tables = $conn->query("SHOW TABLES FROM `" . $database . "`")->fetchAll(PDO::FETCH_COLUMN);
                            if (count($tables) > 0):
                            ?>
                                <div class="table-list">
                                    <h4>Tables:</h4>
                                    <?php foreach ($tables as $table): ?>
                                        <div class="table-item">
                                            <h5><?php echo htmlspecialchars($table); ?></h5>
                                            <?php
                                            // Get table structure
                                            $columns = $conn->query("SHOW COLUMNS FROM `{$database}`.`{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                                            if (count($columns) > 0):
                                            ?>
                                                <div class="table-content">
                                                    <table>
                                                        <thead>
                                                            <tr>
                                                                <?php foreach ($columns as $column): ?>
                                                                    <th><?php echo htmlspecialchars($column['Field']); ?></th>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $rows = $conn->query("SELECT * FROM `{$database}`.`{$table}` LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                                                            foreach ($rows as $row):
                                                            ?>
                                                                <tr>
                                                                    <?php foreach ($row as $value): ?>
                                                                        <td><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                                                    <?php endforeach; ?>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                    <?php if (count($rows) >= 5): ?>
                                                        <p><em>Showing first 5 rows...</em></p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
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