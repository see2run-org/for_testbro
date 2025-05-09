<?php

class User {
    private  $name;
    private  $email;
    private  $password;
    private  $isAdmin;

    public function __construct(string $name, string $email, string $password, bool $isAdmin) {  
        $this->name = $name;
        $this->email = $email;
        
        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long.");
        }

        $this->password = password_hash($password, PASSWORD_DEFAULT);
        $this->isAdmin = $isAdmin;
    }

    function setName($name) {
        $this->name = $name;
    }
}

function saveUserToDatabase($user) {
    $dbHost = getenv('DB_HOST');
    $dbName = getenv('DB_NAME');
    $dbUser = getenv('DB_USER');
    $dbPass = getenv('DB_PASS');
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);


    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, isAdmin) VALUES (?, ?, ?, ?)");
     $stmt->execute([$user->name, $user->email, $user->password, $user->isAdmin]);

}

$name = filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING);
$email = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_EMAIL);
$password = filter_input(INPUT_GET, 'password', FILTER_SANITIZE_STRING);
$isAdmin = filter_input(INPUT_GET, 'isAdmin', FILTER_VALIDATE_BOOLEAN);

if (empty($name) || empty($email) || empty($password) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception("All fields are required and email must be valid.");
}

$user = new User(trim($name), trim($email), trim($password), $isAdmin); 
saveUserToDatabase($user);

echo "User " . htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8') . " has been created successfully!!";