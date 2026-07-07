<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FieldTrack</title>

    <link rel="stylesheet" href="user_panel.css">
</head>
<body>

<!-- ===== DASHBOARD ===== -->
<div id="page-dashboard" class="page active">

    <div class="dash-container">

        <!-- Header -->

        <header>

            <div class="header-left">
                <h1>FieldTrack</h1>
                <p>Field Officer Dashboard</p>
            </div>

            <div class="header-right">
                <span class="date-pill"> 07/07/2026</span>
                <div class="avatar">FO</div>
            </div>

        </header>


        <!-- Welcome -->

        <section class="welcome">

            <div>
                <h2>Welcome, Field Officer </h2>
                <p>Ready to record today's field visits.</p>
            </div>

            <div class="welcome-emoji"></div>

        </section>


        <!-- Dashboard Grid -->

        <div class="dashboard-grid">

            <!-- IN OUT Buttons -->

            <section class="card attendance-buttons">

                <h3>Mark Attendance</h3>

                <div class="btn-row">

                    <button class="in-btn">
                        <span class="btn-icon">⬆</span>
                        IN
                    </button>

                    <button class="out-btn">
                        <span class="btn-icon">⬇</span>
                        OUT
                    </button>

                </div>

            </section>


            <!-- Photo Upload -->

            <section class="card photo-section">

                <h3>Upload Location Photo</h3>

                <!-- Camera -->

                <label class="upload-btn">

                     Take Photo

                    <input
                        type="file"
                        accept="image/*"
                        capture="environment"
                        hidden>

                </label>

                <!-- Gallery -->

                <label class="upload-btn gallery">

                     Choose From Gallery

                    <input
                        type="file"
                        accept="image/*"
                        hidden>

                </label>

            </section>


            <!-- Location -->

            <section class="card location">

                <h3>Current Location</h3>

                <p><span class="tag-label">Latitude</span> Waiting...</p>
                <p><span class="tag-label">Longitude</span> Waiting...</p>
                <p class="status-waiting"><span class="dot"></span> Waiting for Location...</p>

            </section>

        </div>


        <!-- Previous Records -->

        <section class="records">

            <h3>Previous IN / OUT Records</h3>

            <div class="records-grid">

                <div class="record-card record-in">

                    <div class="record-top">
                        <span class="badge badge-in">IN</span>
                        <span class="record-time">09:15 AM</span>
                    </div>

                    <img src="https://placehold.co/300x180" alt="Location Photo">

                    <div class="record-info">
                        <p> 07/07/2026</p>
                        <p> Kandy</p>
                    </div>

                </div>


                <div class="record-card record-out">

                    <div class="record-top">
                        <span class="badge badge-out">OUT</span>
                        <span class="record-time">12:30 PM</span>
                    </div>

                    <img src="https://placehold.co/300x180" alt="Location Photo">

                    <div class="record-info">
                        <p> 07/07/2026</p>
                        <p> Kandy</p>
                    </div>

                </div>

            </div>

        </section>

    </div>

</div>


<!-- Go To Top Button -->
<button id="goTopBtn" title="Go to top">↑</button>

<script>
    // ===== Go To Top Button =====
    const goTopBtn = document.getElementById("goTopBtn");

    window.onscroll = function () {
        if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
            goTopBtn.classList.add("show");
        } else {
            goTopBtn.classList.remove("show");
        }
    };

    goTopBtn.addEventListener("click", function () {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });
</script>

</body>
</html>
