<?php
session_start();
include 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>StockCrop | About Us</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css"> 
    <link rel="icon" type="image/png" href="assets/icon.png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

    <style>
/* --- Global Styles --- */
:root {
  --primary-green: #2f8f3f;
  --secondary-green: #1b5e20;
  --accent: #a8d5ba;
  --light-bg: #f9f9f9;
  --radius: 16px;
  --shadow: rgba(0,0,0,0.12);
  --transition: 0.3s ease;
}

body {
  background-color: var(--light-bg);
  font-family: 'Segoe UI', sans-serif;
}

/* --- Main Wrapper --- */
main {
  padding: 60px 10%;
  max-width: 1200px;
  margin: auto;
}

/* --- Hero Section --- */
.hero {
  background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
  color: white;
  border-radius: var(--radius);
  padding: 80px 30px;
  text-align: center;
  margin-bottom: 60px;
  box-shadow: 0 6px 20px var(--shadow);
}
.hero h1 { font-size: 2.8rem; margin-bottom: 15px; }
.hero p { font-size: 1.2rem; opacity: 0.9; }

/* --- Cards --- */
.card {
  background: white;
  border-radius: var(--radius);
  padding: 30px;
  box-shadow: 0 4px 16px var(--shadow);
  transition: transform var(--transition), box-shadow var(--transition);
}
.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 24px var(--shadow);
}

/* --- About Section --- */
.about-container {
  display: flex;
  flex-wrap: wrap;
  gap: 30px;
  margin-bottom: 50px;
}
.about-text, .about-image {
  flex: 1 1 350px;
}
.about-image img {
  width: 100%;
  border-radius: var(--radius);
  transition: transform var(--transition);
}
.about-image img:hover {
  transform: scale(1.05);
}

/* --- Mission Section --- */
.mission {
  margin-bottom: 50px;
}
.mission-icon {
  font-size: 38px;
  color: var(--primary-green);
  margin-right: 12px;
  vertical-align: middle;
}

/* --- Team Section --- */
.team { text-align: center; margin-bottom: 60px; }
.team-level {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 30px;
  margin-top: 30px;
}
.member {
  background: white;
  border-radius: var(--radius);
  padding: 20px;
  text-align: center;
  width: 220px;
  box-shadow: 0 4px 16px var(--shadow);
  transition: transform var(--transition), box-shadow var(--transition);
}
.member:hover {
  transform: translateY(-8px) scale(1.03);
  box-shadow: 0 8px 24px var(--shadow);
}
.member img {
  width: 100%;
  border-radius: 50%;
  height: auto;
  margin-bottom: 10px;
}
.member h3 { color: var(--primary-green); margin: 10px 0 5px; }
.member p { font-size: 14px; color: #555; }

/* --- Responsive --- */
@media(max-width: 768px){
  .about-container { flex-direction: column; }
  .team-level { flex-direction: column; align-items: center; }
  .member { width: 90%; max-width: 300px; }
}
    </style>
</head>
<body>
<?php include 'navbar.php'; ?> 

<main>
    <section class="hero">
        <h1>About StockCrop</h1>
        <p>Empowering Jamaican farmers and delivering fresh, local produce directly to your table.</p>
    </section>

    <div class="about-container">
        <div class="about-text card">
            <h2>Who We Are</h2>
            <p>StockCrop is a proudly Jamaican platform connecting local farmers directly with customers. Our goal is to make farm-to-table shopping easy, transparent, and sustainable.</p>
        </div>
        <div class="about-image card">
            <img src="assets/farmers.jpg" alt="Local Jamaican farmers working together">
        </div>
    </div>

    <div class="mission card">
        <h2><span class="material-symbols-outlined mission-icon">eco</span>Our Mission</h2>
        <p>To empower Jamaican farmers by providing better market access through technology, while enabling customers to enjoy fresh, locally grown produce. We believe in sustainability, fairness, and community.</p>
    </div>

    <section class="team">
        <h2>Meet Our Team</h2>
        <div class="team-level">
            <div class="member">
                <img src="assets/ceo.jpg" alt="Christina Taylor, CEO">
                <h3>Christina Taylor</h3>
                <p>Founder & CEO</p>
            </div>
        </div>
        <div class="team-level">
            <div class="member">
                <img src="assets/operations.jpg" alt="Darico Powell, Operations Manager">
                <h3>Darico Powell</h3>
                <p>Operations Manager</p>
            </div>
            <div class="member">
                <img src="assets/logistics.jpg" alt="David Martin, Logistics Coordinator">
                <h3>David Martin</h3>
                <p>Logistics Coordinator</p>
            </div>
            <div class="member">
                <img src="assets/techlead.jpg" alt="Britania McGregor, Technology Lead">
                <h3>Britania McGregor</h3>
                <p>Technology Lead</p>
            </div>
            <div class="member">
                <img src="assets/marketing.jpg" alt="Charlon McCarthy, Marketing & Outreach Manager">
                <h3>Charlon McCarthy</h3>
                <p>Marketing & Outreach Manager</p>
            </div>
            <div class="member">
                <img src="assets/finance.jpg" alt="Charma Whorms, Finance & Administration Officer">
                <h3>Charma Whorms</h3>
                <p>Finance & Administration Officer</p>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
