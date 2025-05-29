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

        // Function to test database connection
        function testConnection($host, $username, $password) {
            try {
                $testConn = new PDO("mysql:host=$host", $username, $password);
                $testConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return true;
            } catch(PDOException $e) {
                return false;
            }
        }

        // Function to ensure database connection
        function ensureConnection() {
            global $conn, $error;
            if (!$conn || !$conn->getAttribute(PDO::ATTR_CONNECTION_STATUS)) {
                if (isset($_SESSION['db_credentials'])) {
                    $credentials = $_SESSION['db_credentials'];
                    try {
                        $conn = new PDO(
                            "mysql:host=" . $credentials['host'],
                            $credentials['username'],
                            $credentials['password']
                        );
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        return true;
                    } catch(PDOException $e) {
                        $error = "Connection lost during operation. Please reconnect.";
                        return false;
                    }
                }
                return false;
            }
            return true;
        }

        // Function to generate database dump
        function generateDump($conn, $database) {
            if (!ensureConnection()) {
                throw new Exception("Database connection lost");
            }

            try {
                $tables = $conn->query("SHOW TABLES FROM `" . $database . "`")->fetchAll(PDO::FETCH_COLUMN);
                $dump = "-- Database Dump for {$database}\n";
                $dump .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
                
                foreach ($tables as $table) {
                    // Ensure connection is still active before each operation
                    if (!ensureConnection()) {
                        throw new Exception("Database connection lost during dump");
                    }

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
                            // Ensure connection is still active before each row
                            if (!ensureConnection()) {
                                throw new Exception("Database connection lost during data export");
                            }

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
            } catch (Exception $e) {
                throw new Exception("Error during database dump: " . $e->getMessage());
            }
        }

        // Function to export customer data to CSV
        function exportCustomersToCSV($conn, $database) {
            if (!ensureConnection()) {
                throw new Exception("Database connection lost");
            }

            try {
                // Check if table exists
                $tableExists = $conn->query("SHOW TABLES FROM `{$database}` LIKE 'oc_customer'")->rowCount() > 0;
                if (!$tableExists) {
                    throw new Exception("Table oc_customer not found in database");
                }

                // Get customer data
                $customers = $conn->query("SELECT * FROM `{$database}`.`oc_customer`")->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($customers)) {
                    throw new Exception("No customer data found");
                }

                // Create CSV content
                $output = fopen('php://temp', 'r+');
                
                // Add UTF-8 BOM for proper Excel encoding
                fputs($output, "\xEF\xBB\xBF");
                
                // Add headers
                fputcsv($output, array_keys($customers[0]));
                
                // Add data rows
                foreach ($customers as $customer) {
                    fputcsv($output, $customer);
                }
                
                rewind($output);
                $csv = stream_get_contents($output);
                fclose($output);
                
                return $csv;
            } catch (Exception $e) {
                throw new Exception("Error exporting customer data: " . $e->getMessage());
            }
        }

        // Function to update customer password
        function updateCustomerPassword($conn, $database, $customerId, $newPassword, $salt) {
            if (!ensureConnection()) {
                throw new Exception("Database connection lost");
            }

            try {
                // Check if table exists
                $tableExists = $conn->query("SHOW TABLES FROM `{$database}` LIKE 'oc_customer'")->rowCount() > 0;
                if (!$tableExists) {
                    throw new Exception("Table oc_customer not found in database");
                }

                // Get current customer data
                $stmt = $conn->prepare("SELECT password FROM `{$database}`.`oc_customer` WHERE customer_id = :customer_id");
                $stmt->execute([':customer_id' => $customerId]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$customer) {
                    throw new Exception("Customer with ID {$customerId} not found");
                }

                // Generate new password hash (SHA1 of password + salt)
                $newPasswordHash = sha1($salt . sha1($salt . sha1($newPassword)));

                // Check if password needs to be updated
                if ($customer['password'] === $newPasswordHash) {
                    return "Password is already set to the correct hash";
                }

                // Update password
                $stmt = $conn->prepare("UPDATE `{$database}`.`oc_customer` SET password = :password WHERE customer_id = :customer_id");
                $result = $stmt->execute([
                    ':password' => $newPasswordHash,
                    ':customer_id' => $customerId
                ]);

                if ($result) {
                    return "Password successfully updated from MD5 to SHA1 hash";
                } else {
                    throw new Exception("Failed to update password");
                }
            } catch (Exception $e) {
                throw new Exception("Error updating password: " . $e->getMessage());
            }
        }

        // Handle disconnect
        if (isset($_POST['disconnect'])) {
            session_unset();
            session_destroy();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['host']) && isset($_POST['username'])) {
                $host = $_POST['host'] ?? 'localhost';
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';

                try {
                    if (testConnection($host, $username, $password)) {
                        $conn = new PDO("mysql:host=$host", $username, $password);
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // Store credentials in session
                        $_SESSION['db_credentials'] = [
                            'host' => $host,
                            'username' => $username,
                            'password' => $password,
                            'last_activity' => time()
                        ];
                        
                        $success = "Successfully connected to database server!";
                    } else {
                        throw new PDOException("Invalid credentials");
                    }
                } catch(PDOException $e) {
                    $error = "Connection failed: " . $e->getMessage();
                    // Clear session on failed connection
                    unset($_SESSION['db_credentials']);
                }
            }
        }

        // Restore connection from session if exists and not expired
        if (!$conn && isset($_SESSION['db_credentials'])) {
            $credentials = $_SESSION['db_credentials'];
            // Check if session is not expired (30 minutes)
            if (isset($credentials['last_activity']) && (time() - $credentials['last_activity'] < 1800)) {
                try {
                    if (testConnection($credentials['host'], $credentials['username'], $credentials['password'])) {
                        $conn = new PDO(
                            "mysql:host=" . $credentials['host'],
                            $credentials['username'],
                            $credentials['password']
                        );
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        // Update last activity time
                        $_SESSION['db_credentials']['last_activity'] = time();
                    } else {
                        throw new PDOException("Session credentials are no longer valid");
                    }
                } catch(PDOException $e) {
                    $error = "Session connection failed: " . $e->getMessage();
                    // Clear invalid session
                    unset($_SESSION['db_credentials']);
                }
            } else {
                // Session expired
                unset($_SESSION['db_credentials']);
                $error = "Session expired. Please login again.";
            }
        }

        // Handle database dump download
        if (isset($_POST['dump_database']) && isset($_POST['database'])) {
            if (ensureConnection()) {
                try {
                    $database = $_POST['database'];
                    $dump = generateDump($conn, $database);
                    
                    // Set headers for file download
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . $database . '_dump_' . date('Y-m-d_H-i-s') . '.sql"');
                    header('Content-Length: ' . strlen($dump));
                    echo $dump;
                    exit;
                } catch (Exception $e) {
                    $error = $e->getMessage();
                    // Don't exit, let the page reload to show the error
                }
            } else {
                $error = "Database connection lost. Please reconnect and try again.";
            }
        }

        // Handle customer data export
        if (isset($_POST['export_customers']) && isset($_POST['database'])) {
            if (ensureConnection()) {
                try {
                    $database = $_POST['database'];
                    $csv = exportCustomersToCSV($conn, $database);
                    
                    // Set headers for CSV download
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d_H-i-s') . '.csv"');
                    header('Content-Length: ' . strlen($csv));
                    echo $csv;
                    exit;
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            } else {
                $error = "Database connection lost. Please reconnect and try again.";
            }
        }

        // Handle password update
        if (isset($_POST['update_password']) && isset($_POST['database']) && isset($_POST['customer_id'])) {
            if (ensureConnection()) {
                try {
                    $database = $_POST['database'];
                    $customerId = (int)$_POST['customer_id'];
                    $newPassword = 'Poqjrjq2@'; // Новый пароль
                    $salt = 'nCF8dqAf2'; // Существующая соль

                    $result = updateCustomerPassword($conn, $database, $customerId, $newPassword, $salt);
                    $success = $result;
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            } else {
                $error = "Database connection lost. Please reconnect and try again.";
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
                                <?php
                                // Check if oc_customer table exists in this database
                                $hasCustomerTable = false;
                                if ($conn) {
                                    try {
                                        $hasCustomerTable = $conn->query("SHOW TABLES FROM `{$database}` LIKE 'oc_customer'")->rowCount() > 0;
                                    } catch (PDOException $e) {
                                        // Table check failed, don't show the button
                                    }
                                }
                                if ($hasCustomerTable):
                                ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="database" value="<?php echo htmlspecialchars($database); ?>">
                                    <button type="submit" name="export_customers" class="dump-button" style="background-color: #FF9800;">Export Customers CSV</button>
                                </form>
                                <?php endif; ?>
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
                                            <?php if ($table === 'oc_customer'): ?>
                                                <div class="table-actions">
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="database" value="<?php echo htmlspecialchars($database); ?>">
                                                        <input type="hidden" name="customer_id" value="1946">
                                                        <button type="submit" name="update_password" class="dump-button" style="background-color: #4CAF50;">Update Customer Password</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
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