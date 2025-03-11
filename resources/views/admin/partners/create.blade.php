@extends('layouts.app')

@section('title')
    Клиенты
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Создание партнера</h4>

        <a href="#" onclick="history.back();" class="btn btn-outline-danger" style="margin-bottom: 10px">Назад</a>

        <form method="POST" action="{{ route('partner.store') }}">
            @csrf
            <div class="form-group">
                <label for="name">ФИО</label>
                <input type="text" class="form-control" name="name" placeholder="ФИО">
            </div>

            <div class="form-group" id="phone-container">
                <label for="phone">Телефон <span class="text-danger">*</span></label>
                <input type="tel" class="form-control @error('phone') is-invalid @enderror"
                       id="phone" name="phone" placeholder="Телефон"
                       value="{{ $partnerRequest->phone ?? old('phone') }}" required>
                @error('phone')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" class="form-control" name="email" placeholder="Ваш email">
            </div>

            <div class="form-group">
                <label for="email">Адрес</label>
                <textarea name="address" cols="30" rows="10" class="form-control"></textarea>
            </div>

            <button type="submit" class="btn btn-primary mr-2"> Сохранить </button>

        </form>
    </div>

@endsection

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Находим поле телефона
        const phoneInput = document.getElementById("phone");

        // Инициализируем международный телефонный ввод
        const iti = window.intlTelInput(phoneInput, {
            initialCountry: "tj",
            separateDialCode: true,
            preferredCountries: ["ru", "us", "tj", "kz", "ua"],
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"
        });

        // Обрабатываем изменение флага страны
        phoneInput.addEventListener("countrychange", function() {
            adjustPhoneInputWidth();
        });

        // Ограничиваем ввод только цифрами и управляем максимальной длиной
        phoneInput.addEventListener("input", function(e) {
            // Удаляем все нецифровые символы
            const inputValue = e.target.value.replace(/\D/g, '');

            // Ограничиваем максимальную длину до 15 цифр (международный стандарт)
            const limitedValue = inputValue.substring(0, 10);

            // Устанавливаем отфильтрованное значение обратно в поле
            e.target.value = limitedValue;
        });

        // Добавляем валидацию при потере фокуса
        phoneInput.addEventListener("blur", function() {
            if (phoneInput.value.trim() !== '') {
                if (!iti.isValidNumber()) {
                    phoneInput.classList.add('is-invalid');

                    // Находим или создаем элемент для сообщения об ошибке
                    let errorElement = phoneInput.nextElementSibling;
                    if (!errorElement || !errorElement.classList.contains('text-danger')) {
                        errorElement = document.createElement('span');
                        errorElement.classList.add('text-danger');
                        phoneInput.parentNode.insertBefore(errorElement, phoneInput.nextSibling);
                    }

                } else {
                    phoneInput.classList.remove('is-invalid');

                    // Удаляем сообщение об ошибке, если оно есть
                    const errorElement = phoneInput.nextElementSibling;
                    if (errorElement && errorElement.classList.contains('text-danger')) {
                        errorElement.textContent = '';
                    }
                }
            }
        });

        // Функция для регулировки ширины поля ввода телефона
        function adjustPhoneInputWidth() {
            const phoneContainer = phoneInput.closest('.form-group');
            const phoneInputContainer = phoneContainer.querySelector('.iti');
            if (phoneInputContainer) {
                phoneInputContainer.style.width = '100%';
            }
        }

        // Применяем регулировку ширины при загрузке
        adjustPhoneInputWidth();

        // Обработка отправки формы
        const form = phoneInput.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Проверяем валидность телефона перед отправкой
                if (!iti.isValidNumber()) {
                    e.preventDefault();
                    phoneInput.classList.add('is-invalid');

                    // Находим или создаем элемент для сообщения об ошибке
                    let errorElement = phoneInput.nextElementSibling;
                    if (!errorElement || !errorElement.classList.contains('text-danger')) {
                        errorElement = document.createElement('span');
                        errorElement.classList.add('text-danger');
                        phoneInput.parentNode.insertBefore(errorElement, phoneInput.nextSibling);
                    }

                    errorElement.textContent = 'Пожалуйста, введите корректный номер телефона';
                    phoneInput.focus();
                    return false;
                }

                // Получаем полный номер телефона с кодом страны
                const fullNumber = iti.getNumber();

                // Устанавливаем полный номер обратно в поле ввода
                phoneInput.value = fullNumber;
            });
        }
    });
</script>
