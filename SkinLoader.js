class SkinLoader {
    constructor() {
        this.SKIN_MAGIC = 0x4E494B53; // 'SKIN'
        this.KPACK_MAGIC = 0x4B43504B; // 'KPCK'
        this.name = 'theme.skn';
        this.binary = null;

        // Создаем кеш для изображений, чтобы не загружать их несколько раз
        this.imageCache = {};

        // Структура файла skin
        this.skinStructure = {
            magic: DataType.DWORD,
            version: DataType.DWORD,
            '#params': {
                margin: {
                    height: DataType.DWORD,
                    right: DataType.WORD,
                    left: DataType.WORD,
                    bottom: DataType.WORD,
                    top: DataType.WORD
                },
                active: {
                    inner: DataType.COLOR,
                    outer: DataType.COLOR,
                    frame: DataType.COLOR
                },
                inactive: {
                    inner: DataType.COLOR,
                    outer: DataType.COLOR,
                    frame: DataType.COLOR
                },
                dtp: {
                    size: DataType.DWORD,
                    taskbar: DataType.COLOR,
                    taskbar_text: DataType.COLOR,
                    work_dark: DataType.COLOR,
                    work_light: DataType.COLOR,
                    window_title: DataType.COLOR,
                    work: DataType.COLOR,
                    work_button: DataType.COLOR,
                    work_button_text: DataType.COLOR,
                    work_text: DataType.COLOR,
                    work_graph: DataType.COLOR
                }
            },
            '#buttons:3': {
                type: DataType.DWORD,
                left: DataType.I16,
                top: DataType.I16,
                width: DataType.I16,
                height: DataType.I16
            },
            '#bitmaps:6': {
                kind: DataType.WORD,
                type: DataType.WORD,
                '#data': this.parseBitmaps
            }
        };
    }

    parseBitmaps(skinData, posbm) {
        let width = skinData.getUint32(posbm, true);
        let height = skinData.getUint32(posbm + 4, true);
        let canvas = document.createElement("canvas");

        canvas.width = width;
        canvas.height = height;

        let ctx = canvas.getContext("2d");
        let canvasData = ctx.getImageData(0, 0, width, height);

        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                let i = (x + y * width) * 3;
                let bb = skinData.getUint8(posbm + 8 + i);
                let gg = skinData.getUint8(posbm + 8 + i + 1);
                let rr = skinData.getUint8(posbm + 8 + i + 2);

                let pixelIndex = (x + y * width) * 4;
                canvasData.data[pixelIndex] = rr;
                canvasData.data[pixelIndex + 1] = gg;
                canvasData.data[pixelIndex + 2] = bb;
                canvasData.data[pixelIndex + 3] = 255;
            }
        }
        ctx.putImageData(canvasData, 0, 0);

        return {
            width,
            height,
            base64: canvas.toDataURL()
        };
    }

    applyStyles(element, styles) {
        Object.keys(styles).forEach(key => {
            element.style[key] = styles[key];
        });
    }

    // Функция для загрузки изображения с кешированием
    loadImage(base64) {
        if (base64 in this.imageCache) {
            return this.imageCache[base64];
        } else {
            let img = new Image();
            img.src = base64;
            this.imageCache[base64] = img;
            return img;
        }
    }

    loadWindowStructureTheme(object, structure) {
        const active = object;
        const status = object.dataset.status ?? 'active';
        const activeInner = active.querySelector('.inner');

        this.applyStyles(active, {
            borderColor: structure.params[status].outer,
            backgroundColor: structure.params[status].frame
        });
        this.applyStyles(activeInner, {
            borderColor: structure.params[status].inner,
            backgroundColor: structure.params.dtp.work
        });

        structure.bitmaps.forEach(item => {
            if (item.type == (status == 'active')) {
                let panel = active.querySelector('.panel');
                let button = active.querySelector('.button');
                let left = active.querySelector('.left');

                // Используем кешированное изображение
                let img = this.loadImage(item.data.base64);

                if (item.kind === 3) {
                    this.applyStyles(panel, {
                        backgroundImage: `url(${img.src})`,
                        height: `${item.data.height}px`
                    });
                } else if (item.kind === 2) {
                    this.applyStyles(button, {
                        backgroundImage: `url(${img.src})`,
                        height: `${item.data.height}px`,
                        width: `${item.data.width}px`,
                        right: '-1px'
                    });
                } else if (item.kind === 1) {
                    this.applyStyles(left, {
                        backgroundImage: `url(${img.src})`,
                        height: `${item.data.height}px`,
                        width: `${item.data.width}px`,
                        left: '-1px'
                    });
                }
            }
        });

        structure.buttons.forEach(item => {
            if (item.type === 1) {
                let activeButton = active.querySelector('.min');
                this.applyStyles(activeButton, {
                    top: `${item.top}px`,
                    right: `${-structure.params.margin.left + structure.params.margin.right + item.left}px`,
                    height: `${item.height}px`,
                    width: `${item.width}px`
                });
            } else {
                let activeButton = active.querySelector('.close');
                this.applyStyles(activeButton, {
                    top: `${item.top}px`,
                    right: `${-structure.params.margin.left + structure.params.margin.right + item.left}px`,
                    height: `${item.height}px`,
                    width: `${item.width}px`
                });
            }
        });

        object.querySelectorAll('button').forEach(item => {
            this.applyStyles(item, {
                backgroundColor: structure.params.dtp.work_button,
                color: structure.params.dtp.work_button_text
            });
        });

        object.querySelectorAll('span').forEach(item => {
            item.style.color = structure.params.dtp.work_text;
        });
    }

    loadStructureTheme(structure) {
        document.querySelectorAll('.window').forEach(w => {
            let title = w.querySelector('.title');
            title.style.color = structure.params.dtp.window_title;
            title.style.left = `${structure.params.margin.left}px`;
            this.loadWindowStructureTheme(w, structure);
        });
    }

    base64ToUint8Array(base64) {
        const binaryString = atob(base64.split(',')[1]);
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        return bytes;
    }

    base64ToRGBArray(base64) {
        const img = new Image();
        img.src = base64;

        // Создаем canvas и контекст
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // Устанавливаем размер canvas равным размеру изображения
        canvas.width = img.width;
        canvas.height = img.height;

        // Рисуем изображение на canvas
        ctx.drawImage(img, 0, 0);

        // Получаем данные изображения
        const imageData = ctx.getImageData(0, 0, img.width, img.height);
        const data = imageData.data;

        // Создаем массив RGB
        const rgbArray = [];
        for (let i = 0; i < data.length; i += 4) {
            const rr = data[i];
            const gg = data[i + 1];
            const bb = data[i + 2];
            rgbArray.push(bb, gg, rr);
        }

        return rgbArray;
    }

    save(structure) {
        let packData;
        try {
            const kpacker = new KPacker();
            packData = kpacker.pack(this.binary); // compress data
        } catch (error) {
            alert(`Packing error: ${error}`);
            return;
        }

        // Create Blob and download link
        const blob = new Blob([packData], { type: 'application/octet-stream' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', this.name);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    read(skinData) {
        let magic = skinData.getUint32(0, true);
        if (magic === this.KPACK_MAGIC) {
            try {
                const kpacker = new KPacker();
                this.binary = kpacker.unpack(skinData);
            } catch (error) {
                alert(`Unpacking error: ${error}`);
                return;
            }
        }

        // Используем BinaryParser для парсинга данных скина
        const parser = new BinaryParser(this.binary.buffer, this.skinStructure, (structure)=>{
            this.loadStructureTheme.call(this, structure);
        });
        const data = parser.parse();

        if (this.SKIN_MAGIC === data.magic) {
            try {
                this.loadStructureTheme(data);
                document.querySelectorAll('.window').forEach(window => window.style.display = 'block');
                return data;
            } catch (error) {
                alert(`Unpacking error: ${error}`);
                return;
            }
        }
    }
}