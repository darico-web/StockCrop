<?php
session_start();
include 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>StockCrop | Contact Us</title>
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

        /* --- Main --- */
        main { padding:60px 10%; max-width:1200px; margin:auto; }

        /* --- Hero Section --- */
        .hero {
        background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
        color:white;
        border-radius: var(--radius);
        padding:80px 30px;
        text-align:center;
        margin-bottom:60px;
        box-shadow: 0 6px 20px var(--shadow);
        }
        .hero h1 { font-size:2.8rem; margin-bottom:15px; }
        .hero p { font-size:1.2rem; opacity:0.9; }

        /* --- Cards --- */
        .card {
        background:white;
        border-radius: var(--radius);
        padding:30px;
        box-shadow: 0 4px 16px var(--shadow);
        transition: transform var(--transition), box-shadow var(--transition);
        }
        .card:hover { transform: translateY(-5px); box-shadow:0 8px 24px var(--shadow); }

        /* --- About Page --- */
        .about-container { display:flex; flex-wrap:wrap; gap:30px; margin-bottom:50px; }
        .about-text, .about-image { flex:1 1 300px; }
        .about-image img { width:100%; border-radius: var(--radius); transition: transform var(--transition); }
        .about-image img:hover { transform: scale(1.05); }

        /* --- Team Section --- */
        .team { text-align:center; margin-bottom:60px; }
        .team-level { display:flex; flex-wrap:wrap; justify-content:center; gap:30px; margin-top:30px; }
        .member {
        background:white; border-radius:var(--radius); padding:20px;
        text-align:center; width:220px; box-shadow: 0 4px 16px var(--shadow);
        transition: transform var(--transition), box-shadow var(--transition);
        }
        .member:hover { transform: translateY(-8px) scale(1.03); box-shadow:0 8px 24px var(--shadow); }
        .member img { width:100%; border-radius:50%; height:auto; margin-bottom:10px; }
        .member h3 { color: var(--primary-green); margin:10px 0 5px; }
        .member p { font-size:14px; color:#555; }

        /* --- Contact Form --- */
        .contact-card { max-width:800px; margin:auto; margin-bottom:50px; }
        .input-group { position:relative; margin-bottom:20px; }
        .input-group input, .input-group textarea {
        width:100%; padding:14px; border:1px solid #ccc; border-radius: var(--radius); font-size:16px;
        background:transparent; transition: var(--transition);
        }
        .input-group label {
        position:absolute; top:14px; left:14px; color:#666; pointer-events:none;
        background:white; padding:0 4px; transition:0.2s;
        }
        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label,
        .input-group textarea:focus + label,
        .input-group textarea:not(:placeholder-shown) + label {
        top:-8px; font-size:12px; color: var(--primary-green);
        }
        button {
        background: var(--primary-green); color:white; border:none; padding:15px; border-radius: var(--radius);
        font-size:18px; font-weight:600; cursor:pointer; transition: var(--transition);
        }
        button:hover { background: var(--secondary-green); transform:translateY(-2px); }


        /* --- Responsive --- */
        @media(max-width:768px){
        header { flex-direction:column; align-items:flex-start; }
        header nav { margin-top:10px; }
        .about-container { flex-direction:column; }
        .team-level { flex-direction:column; align-items:center; }
        .member { width:90%; max-width:300px; }
        }

    </style>
</head>
<body>
    <?php include 'navbar.php';?> 

    <main>
        <section class="hero">
            <h1>Get in Touch</h1>
            <p>We are here to help — questions, feedback, or partnerships.</p>
        </section>

        <div class="contact-card card">
            <h2 class="mb-4">Contact Us</h2>
            <form>
                <div class="input-group">
                    <input type="text" id="name" placeholder=" " required>
                    <label for="name">Full Name</label>
                </div>
                <div class="input-group">
                    <input type="email" id="email" placeholder=" " required>
                    <label for="email">Email Address</label>
                </div>
                <div class="input-group">
                    <textarea id="message" placeholder=" " required></textarea>
                    <label for="message">Message</label>
                </div>
                <button type="submit">Send Message</button>
            </form>

            <div style="margin-top:25px; border-radius:12px; overflow:hidden;">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18..." width="100%" height="250" style="border:0;" loading="lazy"></iframe>
            </div>
        </div>
    </main>



    <?php
        include 'footer.php';
    ?>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</html>