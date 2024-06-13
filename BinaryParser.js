const DataType = {
    BYTE: 1,
    CHAR: -1,
    WORD: 2,
    DWORD: 4,
    INT: -4,
    STR: 5,
    PTR: 6,
};

class BinaryParser {
    constructor(data, structure) {
        this.data = new DataView(data);
        this.structure = structure;
        this.offset = 0;
        this.parsedData = this.parseStructure(structure);
    }

    parse() {
        return this.parsedData;
    }

    parseStructure(structure) {
        let result = {};
        for (let [key, type] of Object.entries(structure)) {
            let value;
            const saveOffset = this.offset;
            let isPointer = key.startsWith('#');

            if (isPointer) {
                key = key.slice(1);
                this.offset = this.data.getUint32(saveOffset, true);
                value = typeof type === 'object' ? this.parseStructure(type) : this.parseValue(type);
                this.offset = saveOffset;
            } else {
                value = typeof type === 'object' ? this.parseStructure(type) : this.parseValue(type);
            }

            Object.defineProperty(result, key, {
                get: () => value,
                set: (newValue) => {
                    this.offset = isPointer ? this.data.getUint32(saveOffset, true) : saveOffset;
                    this.writeValue(type, newValue);
                    value = newValue;
                    this.offset = saveOffset;
                },
                enumerable: true,
            });
        }
        return result;
    }

    parseValue(type) {
        if (this.offset + Math.abs(type) > this.data.byteLength) {
            throw new RangeError("Offset is outside the bounds of the DataView");
        }

        let value;
        switch (type) {
            case DataType.BYTE:
                value = this.data.getUint8(this.offset);
                this.offset += 1;
                break;
            case DataType.CHAR:
                value = String.fromCharCode(this.data.getInt8(this.offset));
                this.offset += 1;
                break;
            case DataType.STR:
                value = this.readString();
                break;
            case DataType.WORD:
                value = this.data.getUint16(this.offset, true);
                this.offset += 2;
                break;
            case DataType.DWORD:
                value = this.data.getUint32(this.offset, true);
                this.offset += 4;
                break;
            case DataType.INT:
                value = this.data.getInt32(this.offset, true);
                this.offset += 4;
                break;
            default:
                throw new Error(`Unknown type: ${type}`);
        }
        return value;
    }

    readString() {
        let str = '';
        while (this.offset < this.data.byteLength) {
            let char = this.data.getInt8(this.offset++);
            if (char === 0) break;
            str += String.fromCharCode(char);
        }
        return str;
    }

    writeValue(type, value) {
        switch (type) {
            case DataType.BYTE:
                this.data.setUint8(this.offset, value);
                this.offset += 1;
                break;
            case DataType.CHAR:
                this.data.setInt8(this.offset, value.charCodeAt(0));
                this.offset += 1;
                break;
            case DataType.STR:
                for (let char of value) {
                    this.data.setInt8(this.offset++, char.charCodeAt(0));
                }
                this.data.setInt8(this.offset++, 0);
                break;
            case DataType.WORD:
                this.data.setUint16(this.offset, value, true);
                this.offset += 2;
                break;
            case DataType.DWORD:
                this.data.setUint32(this.offset, value, true);
                this.offset += 4;
                break;
            case DataType.INT:
                this.data.setInt32(this.offset, value, true);
                this.offset += 4;
                break;
            default:
                throw new Error(`Unknown type: ${type}`);
        }
    }
}
