<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <style>
        .slider {

            overflow: hidden;
            position: relative;
        }

        .slides {
            display: flex;
            transition: transform 0.6s ease-in-out;
        }

        .slide {
            min-width: 100%;
            transition: 0.5s;
        }

        .slide img {
            width: 100%;
            /* height: auto; */
            max-height: 590px;
            border-radius: 10px;
            object-fit: contain;
        }

        .navigation-manual {
            position: absolute;
            width: 100%;
            display: flex;
            justify-content: center;
            bottom: 10px;
        }

        .manual-btn {
            border: 1px solid black;
            padding: 4px;
            border-radius: 50%;
            cursor: pointer;
            transition: 0.4s;
        }

        .manual-btn:not(:last-child) {
            margin-right: 10px;
        }

        .manual-btn:hover,
        .manual-btn.active {
            background: red;
        }
    </style>
</head>

<body>
    <section class="container mt-5 mb-5 pb-3">
        <div class="slider">
            <div class="slides" id="slides">
                <div class="slide">
                    <a href=""><img src="./img/imgslider/banner 1.png" alt="Slide 1"></a>
                </div>
                <div class="slide">
                    <a href=""><img src="./img/imgslider/banner 2.png" alt="Slide 2"></a>
                </div>
                <div class="slide">
                    <a href=""><img src="./img/imgslider/banner 3.png" alt="Slide 3"></a>
                </div>
                <div class="slide">
                    <a href=""><img src="./img/imgslider/banner 4.png" alt="Slide 4"></a>
                </div>
            </div>
            <!-- Manual Navigation -->
            <div class="navigation-manual">
                <label class="manual-btn" onclick="moveToSlide(0)"></label>
                <label class="manual-btn" onclick="moveToSlide(1)"></label>
                <label class="manual-btn" onclick="moveToSlide(2)"></label>
                <label class="manual-btn" onclick="moveToSlide(3)"></label>
            </div>
        </div>
    </section>
    <script>
        let slides = document.getElementById("slides");
        let currentIndex = 0;
        let forward = true;
        const totalSlides = document.querySelectorAll(".slide").length;

        function moveToSlide(index) {
            slides.style.transform = "translateX(" + -index * 100 + "%)";
            currentIndex = index;
            updateActiveButton();
        }

        function updateActiveButton() {
            let buttons = document.querySelectorAll(".manual-btn");
            buttons.forEach((button, index) => {
                if (index === currentIndex) {
                    button.classList.add("active");
                } else {
                    button.classList.remove("active");
                }
            });
        }

        function autoSlide() {
            if (forward) {
                if (currentIndex < totalSlides - 1) {
                    currentIndex++;
                } else {
                    forward = false;
                    currentIndex--;
                }
            } else {
                if (currentIndex > 0) {
                    currentIndex--;
                } else {
                    forward = true;
                    currentIndex++;
                }
            }
            moveToSlide(currentIndex);
        }

        setInterval(autoSlide, 3000);
        updateActiveButton();
    </script>
</body>

</html>