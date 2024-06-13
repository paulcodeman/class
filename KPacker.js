class KPacker {
    static KPACK_MAGIC = 0x4B43504B;
    static KPACK_PACKED_DATA_OFFSET = 12;
    static LZMA_HEADER_SIZE = 14;
    static LZMA_HEADER_PROPS = (2 * 5 + 0) * 9 + 3;
    static LZMA_DICT_SIZE = 0x10000;
    static LZMA_COMPRESSION_FLAG = 1;

    constructor() {}

    unpack(kpack_data) {
        if (!kpack_data || kpack_data.byteLength < KPacker.KPACK_PACKED_DATA_OFFSET) {
            throw new Error("Invalid kpack_data input");
        }

        const kpack_header = new DataView(kpack_data.buffer, kpack_data.byteOffset, KPacker.KPACK_PACKED_DATA_OFFSET);

        if (kpack_header.getUint32(0, true) !== KPacker.KPACK_MAGIC) {
            throw new Error("Invalid magic number in kpackHeader.");
        }

        if (kpack_header.getUint32(8, true) !== KPacker.LZMA_COMPRESSION_FLAG) {
            throw new Error("Unsupported compression type!");
        }

        let dict_size = KPacker.LZMA_DICT_SIZE;
        while (dict_size < kpack_header.getUint32(4, true)) {
            dict_size <<= 1;
        }

        const lzma_header_props = KPacker.LZMA_HEADER_PROPS;
        const lzma_header_unpack_size = BigInt(kpack_header.getUint32(4, true));

        const packed_data = new DataView(kpack_data.buffer, kpack_data.byteOffset + KPacker.KPACK_PACKED_DATA_OFFSET);
        const lzma_data = new Uint8Array(packed_data.byteLength + KPacker.LZMA_HEADER_SIZE);
        const lzma_data_view = new DataView(lzma_data.buffer);

        lzma_data_view.setUint8(0, lzma_header_props);
        lzma_data_view.setUint32(1, dict_size, true);
        lzma_data_view.setBigUint64(5, lzma_header_unpack_size, true);

        // Required transformation
        const tmp = packed_data.getUint32(0, true);
        packed_data.setUint32(0, tmp, false);

        lzma_data.set(new Uint8Array(packed_data.buffer, packed_data.byteOffset, packed_data.byteLength), KPacker.LZMA_HEADER_SIZE);

        const unpack_array = LZMA.decompress(lzma_data);
        return new DataView(new Uint8Array(unpack_array).buffer);
    }

    pack(data) {
        if (!(data instanceof DataView)) {
            throw new Error("Input data must be a DataView instance.");
        }

        const unpack_size = data.byteLength;

        let compressed_data;
        try {
            // Convert DataView to Uint8Array before compression
            compressed_data = LZMA.compress(new Uint8Array(data.buffer, data.byteOffset, unpack_size), KPacker.LZMA_COMPRESSION_FLAG);
        } catch (error) {
            throw new Error("LZMA compression failed: " + error.message);
        }

        compressed_data = compressed_data.slice(KPacker.LZMA_HEADER_SIZE);

        const packed_data = new Uint8Array(compressed_data.length + KPacker.KPACK_PACKED_DATA_OFFSET);
        const packed_data_view = new DataView(packed_data.buffer);

        packed_data_view.setUint32(0, KPacker.KPACK_MAGIC, true);
        packed_data_view.setUint32(4, unpack_size, true);
        packed_data_view.setUint32(8, KPacker.LZMA_COMPRESSION_FLAG, true); // Compression flag

        // Set compressed data in the correct offset
        packed_data.set(compressed_data, KPacker.KPACK_PACKED_DATA_OFFSET);

        // Required transformation
        const tmp = packed_data_view.getUint32(KPacker.KPACK_PACKED_DATA_OFFSET, false);
        packed_data_view.setUint32(KPacker.KPACK_PACKED_DATA_OFFSET, tmp, true);

        // You don't need to create a new DataView here
        return packed_data_view;
    }
}