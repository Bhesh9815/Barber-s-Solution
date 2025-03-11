<?php
session_start();
?>

<header>
    <div class="logo">
        <img src="logo.png" alt="Logo">
        <span>Barber's Solution</span>
    </div>
    <nav>
        <a href="#home">Home</a>
        <a href="#about">About</a>
        <a href="#services">Services</a>
        <a href="#contact">Contact</a>

        <?php if (isset($_SESSION['username'])): ?>
            <div class="user-menu">
                <span class="username">Welcome, <?= htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        <?php else: ?>
            <a href="#login">Login/Register</a>
        <?php endif; ?>
    </nav>
</header>
