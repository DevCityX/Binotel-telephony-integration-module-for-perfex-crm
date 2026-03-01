<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<style>
    /* Плаваюча іконка трубочки */
    .binotel-floating-icon {
      position: fixed;
      top: 80px;
      right: 30px;
      z-index: 9999;
      cursor: pointer;
    }
    .binotel-button {
      background-color: #fff;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 5px rgba(0,0,0,0.3);
      transition: background-color 0.3s, box-shadow 0.3s;
      border: none;
      cursor: pointer;
    }
    .binotel-button:hover {
      background-color: #f9f9f9;
      box-shadow: 0 2px 8px rgba(0,0,0,0.4);
    }
    #binotel_icon {
      color: transparent; 
      -webkit-text-stroke: 1px #000;
      font-size: 20px;
      transition: transform 0.2s, -webkit-text-stroke 0.3s;
    }
    .binotel-button:hover #binotel_icon {
      transform: scale(1.1);
    }
    /* Випадаючий список дзвінків */
    #binotel_dropdown {
      display: none;
      position: absolute;
      top: 60px;
      right: 0;
      width: 300px;
      max-height: 400px;
      overflow-y: auto;
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      border-radius: 5px;
      padding: 0;
      margin: 0;
      list-style: none;
      z-index: 99999;
    }
    /* Анімація вібрації */
    @keyframes vibrate {
      0% { transform: translate(0); }
      20% { transform: translate(-2px, 2px); }
      40% { transform: translate(-2px, -2px); }
      60% { transform: translate(2px, 2px); }
      80% { transform: translate(2px, -2px); }
      100% { transform: translate(0); }
    }
    .vibrate {
      animation: vibrate 0.5s infinite;
    }
    /* Контейнер клавіатури (розташовується в тому ж місці, що й список) */
    #dialpadContainer {
      display: none;
      position: absolute;
      top: 60px;
      right: 0;
      width: 300px;
      background-color: #fff;
      box-shadow: 0 2px 8px rgba(0,0,0,0.4);
      border-radius: 5px;
      padding: 10px;
      z-index: 100000;
    }
    #dialpadContainer h4 {
      margin: 0 0 10px 0;
      font-size: 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .dialpad-close {
      cursor: pointer;
      font-size: 20px;
      color: #999;
    }
    .dialpad-close:hover {
      color: #333;
    }
    .dialpad-input {
      width: 100%;
      font-size: 18px;
      padding: 5px;
      text-align: center;
      margin-bottom: 10px;
    }
    .dialpad-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 5px;
      margin-bottom: 10px;
    }
    .dialpad-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      background-color: #f0f0f0;
      border-radius: 5px;
      cursor: pointer;
      padding: 10px 0;
    }
    .dialpad-btn:hover {
      background-color: #e0e0e0;
    }
    .dialpad-actions {
      display: flex;
      justify-content: space-between;
      gap: 5px;
    }
    .dialpad-clear-btn,
    .dialpad-call-btn {
      flex: 1;
      border-radius: 5px;
      padding: 10px 0;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      text-align: center;
      color: #fff;
      border: none;
    }
    .dialpad-clear-btn {
      background-color: #6c757d;
    }
    .dialpad-clear-btn:hover {
      background-color: #5a6268;
    }
    .dialpad-call-btn {
      background-color: #28a745;
    }
    .dialpad-call-btn:hover {
      background-color: #218838;
    }
    .dialpad-call-btn .spinner-border {
      display: none;
      width: 1rem;
      height: 1rem;
      margin-right: 5px;
      vertical-align: text-bottom;
      border: 0.15em solid currentColor;
      border-right-color: transparent;
      border-radius: 50%;
      animation: 0.75s linear infinite spinner-border;
    }
    @keyframes spinner-border {
      to { transform: rotate(360deg); }
    }
    
    /* Стилі для кнопки "Подзвонити" у списку */
    .call-button {
      background-color: #fff;
      border: 1px solid #000;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      cursor: pointer;
      outline: none;
      margin-top: 5px;
    }
    .call-button i {
      color: #000;
      font-size: 16px;
    }
</style>

<!-- Аудіо для звуку дзвінка та звук цифр -->
<audio id="binotel_sound" src="https://actions.google.com/sounds/v1/alarms/bugle_tune.ogg" preload="auto"></audio>
<audio id="digitSound" src="https://actions.google.com/sounds/v1/cartoon/wood_plank_flicks.ogg" preload="auto"></audio>

<div id="binotel_icon_container" class="binotel-floating-icon">
    <!-- Кнопка трубочки -->
    <button class="binotel-button" id="binotel_floating_button" data-toggle="tooltip" title="Дзвінки Binotel">
        <i id="binotel_icon" class="fa fa-phone" aria-hidden="true"></i>
    </button>
    <!-- Випадаючий список дзвінків -->
    <ul id="binotel_dropdown"></ul>
    <!-- Контейнер клавіатури -->
    <div id="dialpadContainer">
      <h4>
        Набір номера
        <span class="dialpad-close" onclick="closeDialpad()">&times;</span>
      </h4>
      <input type="text" id="dialpadInput" class="dialpad-input" placeholder="Введіть номер" readonly>
      <div class="dialpad-grid">
        <div class="dialpad-btn" data-value="1">1</div>
        <div class="dialpad-btn" data-value="2">2</div>
        <div class="dialpad-btn" data-value="3">3</div>
        <div class="dialpad-btn" data-value="4">4</div>
        <div class="dialpad-btn" data-value="5">5</div>
        <div class="dialpad-btn" data-value="6">6</div>
        <div class="dialpad-btn" data-value="7">7</div>
        <div class="dialpad-btn" data-value="8">8</div>
        <div class="dialpad-btn" data-value="9">9</div>
        <div class="dialpad-btn" data-value="*">*</div>
        <div class="dialpad-btn" data-value="0">0</div>
        <div class="dialpad-btn" data-value="#">#</div>
      </div>
      <div class="dialpad-actions">
        <button class="dialpad-clear-btn" id="dialpadClearBtn">Очистити</button>
        <button class="dialpad-call-btn" id="dialpadCallBtn">
          <span class="spinner-border" id="callSpinner"></span>
          <span class="call-text">Виклик</span>
        </button>
      </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    var soundPlayed = false;
    var icon = document.getElementById("binotel_icon");
    var dropdownMenu = document.getElementById("binotel_dropdown");
    var binotelButton = document.getElementById("binotel_floating_button");

    // Елементи для клавіатури
    var dialpadContainer = document.getElementById("dialpadContainer");
    var dialpadInput = document.getElementById("dialpadInput");
    var dialpadCallBtn = document.getElementById("dialpadCallBtn");
    var dialpadClearBtn = document.getElementById("dialpadClearBtn");
    var callSpinner = document.getElementById("callSpinner");
    var callText = dialpadCallBtn ? dialpadCallBtn.querySelector(".call-text") : null;
    var digitSound = document.getElementById("digitSound");

    /**
     * Оновлює список дзвінків через AJAX.
     */
    function updateBinotelIcon(){
        fetch("<?php echo admin_url('binotel_integration/binotel_admin/get_binotel_calls_list'); ?>")
            .then(response => response.json())
            .then(data => {
                // Перевірка на нові дзвінки
                if(icon){
                    if(data.hasNew && data.newCallColor){
                        icon.classList.add("vibrate");
                        icon.style.webkitTextStroke = "1px " + data.newCallColor;
                        if(!soundPlayed){
                            var audio = document.getElementById("binotel_sound");
                            if(audio){
                                audio.play();
                                soundPlayed = true;
                            }
                        }
                    } else {
                        icon.classList.remove("vibrate");
                        icon.style.webkitTextStroke = "1px #000";
                        soundPlayed = false;
                    }
                }
                // Вставляємо HTML у dropdown
                if(dropdownMenu){
                    dropdownMenu.innerHTML = data.html;

                    // Знайти кнопки «Подзвонити» і підвісити їм обробники
                    var callButtons = dropdownMenu.querySelectorAll('.call-button');
                    callButtons.forEach(function(btn){
                        btn.addEventListener('click', function(e){
                            e.preventDefault();
                            var phone = this.getAttribute('data-phone');
                            if(!phone) return;

                            // Зберігаємо оригінальний HTML (іконку телефону)
                            var originalHTML = this.innerHTML;
                            // Замінимо іконку на спінер
                            this.innerHTML = '<i class="fa fa-spinner fa-spin" style="color: #000; font-size: 16px;"></i>';
                            this.disabled = true;

                            // Викликаємо makeBinotelCall з callback, щоб повернути кнопку назад
                            makeBinotelCall(phone, this, originalHTML);
                        });
                    });

                    // Якщо є кнопка «Набрати номер», підвісимо її обробник
                    var dialpadBtn = dropdownMenu.querySelector('.dialpad-floating-btn');
                    if(dialpadBtn){
                        dialpadBtn.addEventListener('click', function(e){
                            e.preventDefault();
                            dropdownMenu.style.display = 'none';
                            openDialpad();
                        });
                    }
                }
            })
            .catch(error => {
                console.error("Error updating binotel calls:", error);
            });
    }

    // Викликаємо відразу і кожні 10 секунд
    updateBinotelIcon();
    setInterval(updateBinotelIcon, 10000);

    /**
     * При кліку на основну трубочку – показати/сховати список + позначити як прочитані
     */
    if(binotelButton && dropdownMenu){
        binotelButton.addEventListener("click", function(e){
            e.preventDefault();
            // Позначаємо всі дзвінки як прочитані
            fetch("<?php echo admin_url('binotel_integration/binotel_admin/mark_binotel_notifications_read'); ?>")
                .then(response => response.json())
                .then(data => {
                    updateBinotelIcon();
                })
                .catch(error => {
                    console.error("Error marking notifications as read:", error);
                });

            // Показати/сховати список
            if(dropdownMenu.style.display === "none" || dropdownMenu.style.display === ""){
                dropdownMenu.style.display = "block";
                // Якщо відкрити список – сховаємо клавіатуру
                if(dialpadContainer) dialpadContainer.style.display = "none";
            } else {
                dropdownMenu.style.display = "none";
            }
        });
    }

    // ---------------------
    // Логіка клавіатури
    // ---------------------
    window.openDialpad = function(){
        if(dialpadContainer) dialpadContainer.style.display = "block";
    };
    window.closeDialpad = function(){
        if(dialpadContainer) dialpadContainer.style.display = "none";
        if(dialpadInput) dialpadInput.value = "";
        resetCallButton();
    };

    // Обробники для цифр
    var dialpadBtns = document.querySelectorAll('.dialpad-btn');
    dialpadBtns.forEach(function(btn){
        btn.addEventListener('click', function(){
            var val = this.getAttribute('data-value');
            if(dialpadInput) dialpadInput.value += val;
            if(digitSound){
                digitSound.currentTime = 0;
                digitSound.play();
            }
        });
    });

    // Кнопка "Очистити"
    if(dialpadClearBtn){
        dialpadClearBtn.addEventListener('click', function(){
            if(dialpadInput) dialpadInput.value = "";
        });
    }

    // Кнопка "Виклик" у клавіатурі
    if(dialpadCallBtn){
        dialpadCallBtn.addEventListener('click', function(){
            if(!dialpadInput) return;
            var phoneNumber = dialpadInput.value.trim();
            if(!phoneNumber){
                alert("Введіть номер телефону");
                return;
            }
            startCallButton();
            $.post("<?php echo admin_url('binotel_integration/make_call'); ?>", { phone: phoneNumber }, function(response){
                if(response.status === 'success'){
                    alert('Виклик здійснено для номера: ' + phoneNumber);
                    closeDialpad();
                } else {
                    alert('Помилка виклику: ' + response.message);
                    resetCallButton();
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error("Error making call:", error);
                resetCallButton();
            });
        });
    }

    function startCallButton(){
        if(callSpinner) callSpinner.style.display = 'inline-block';
        if(callText) callText.textContent = 'Викликаємо...';
        if(dialpadCallBtn) dialpadCallBtn.disabled = true;
        if(dialpadClearBtn) dialpadClearBtn.disabled = true;
    }
    function resetCallButton(){
        if(callSpinner) callSpinner.style.display = 'none';
        if(callText) callText.textContent = 'Виклик';
        if(dialpadCallBtn) dialpadCallBtn.disabled = false;
        if(dialpadClearBtn) dialpadClearBtn.disabled = false;
    }

    /**
     * Функція виклику для кнопок у списку
     * Додаємо параметри btn і originalHTML, щоб повертати вигляд кнопки
     */
    window.makeBinotelCall = function(phone, btn, originalHTML) {
        $.post("<?php echo admin_url('binotel_integration/make_call'); ?>", { phone: phone }, function(response){
            if(response.status === 'success'){
                alert('Виклик здійснено для номера: ' + phone);
            } else {
                alert('Помилка виклику: ' + response.message);
            }
            // Повертаємо кнопку
            if(btn){
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        }, 'json').fail(function(xhr, status, error){
            console.error("Error making call:", error);
            // Повертаємо кнопку
            if(btn){
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        });
    };
});
</script>
