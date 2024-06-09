function findElementsByRegex(selector, regex) {
    // Находим все элементы по заданному селектору
    const elements = document.querySelectorAll(selector);
    
    // Фильтруем элементы по регулярному выражению
    const matchingElements = Array.from(elements).filter(item => regex.test(item.innerText.trim()));
    
    return matchingElements;
}

async function mousedownParentsUntilRoot(element) {
    let currentElement = element;

    while (currentElement) {
        // Создаем событие mousedown
        const event = new MouseEvent('mousedown', {
            bubbles: true,
            cancelable: true,
            view: window
        });

        // Имитируем mousedown по текущему элементу
        currentElement.dispatchEvent(event);

        // Переходим к родительскому элементу
        currentElement = currentElement.parentNode;
    }
}

// Функция для ожидания появления элемента с заданным селектором
async function waitForElement(selector) {
    return new Promise(resolve => {
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                const element = document.querySelector(selector);
                if (element) {
                    observer.disconnect();
                    resolve(element);
                }
            });
        });

        // Начинаем наблюдение за изменениями в документе
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Проверяем наличие элемента сразу
        const element = document.querySelector(selector);
        if (element) {
            observer.disconnect();
            resolve(element);
        }
    });
}

async function processElementsWithDelay(elements, delay) {
    for (const element of elements) {
        await mousedownParentsUntilRoot(element);
        await new Promise(resolve => setTimeout(resolve, delay));
        
        // Ожидаем появления кнопки OK
        const okButton = await waitForElement('.Button.default.primary.text');
        
        // Выполняем mousedownParentsUntilRoot для кнопки OK
        await mousedownParentsUntilRoot(okButton);
    }
}

// Пример использования
const regex = /\+\d+\.\d{2}RUB/; // Регулярное выражение для поиска строк в формате +1.23RUB
const matchingElements = findElementsByRegex('.inline-button-text', regex);

// Обработка найденных элементов с задержкой 500 мс между вызовами
processElementsWithDelay(matchingElements, 500);
