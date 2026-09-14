<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useForm, router, Head, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import JsBarcode from 'jsbarcode';
import { 
    Package, Plus, Search, Layers, Tag, Edit3, Trash2, 
    Check, AlertTriangle, ArrowUpDown, X, Sparkles, PlusCircle, PackagePlus,
    ChevronDown, CheckCircle2, QrCode, Barcode, Printer, Copy,
    ClipboardCheck, History, ArrowUpRight, ArrowDownRight, RefreshCw, AlertCircle, BookmarkCheck,
    Pencil, Shapes, SlidersHorizontal, Ruler, Boxes, FileSpreadsheet, FileText, Download
} from 'lucide-vue-next';

const props = defineProps({
    products: Array,
    categories: Array,
    brands: Array,
    units: Array,
    stockLogs: Array,
    user: Object,
});

const page = usePage();
const canSeeCostPrice = computed(() => {
    const role = props.user?.role || page.props.auth?.user?.role;
    if (role === 'admin' || role === 'gudang') return true;
    if (role === 'kasir') {
        return String(page.props.settings?.kasir_can_see_cost_price || '0') === '1';
    }
    return false;
});

const activeTab = ref('catalog'); // 'catalog', 'opname', 'logs'
const searchQuery = ref('');
const opnameCategoryFilter = ref('all');
const isAddModalOpen = ref(false);
const isAdjustModalOpen = ref(false);
const selectedProduct = ref(null);

// Barcode Print Modal State
const isBarcodeModalOpen = ref(false);
const barcodeProduct = ref(null);
const barcodeSelectedUnit = ref(null);
const barcodePrintCount = ref(6);
const barcodePaperType = ref('thermal_33x19'); // 'thermal_33x19', 'thermal_50x30', 'thermal_40x30', 'grid_a4'

// Watcher to re-render barcodes whenever count, paper type, or product changes
watch([barcodePrintCount, barcodePaperType, barcodeProduct, barcodeSelectedUnit], () => {
    if (isBarcodeModalOpen.value) {
        renderBarcodes();
    }
});

// Combobox States
const isBrandDropdownOpen = ref(false);
const brandSearchQuery = ref('');
const isCategoryDropdownOpen = ref(false);
const categorySearchQuery = ref('');

// Form Tambah Produk Baru
const addProductForm = useForm({
    sku: '',
    barcode: '',
    name: '',
    category_id: null,
    brand_id: null,
    min_stock: 0,
    stock_physical: 0,
    description: '',
    units: [
        {
            unit_name: 'Pcs',
            conversion_ratio: 1,
            cost_price: 0,
            price_retail: 0,
            is_base_unit: true,
        }
    ]
});

// Opname Inline State Map (productId -> { physicalInput, reason })
const opnameInputs = ref({});
props.products.forEach(p => {
    opnameInputs.value[p.id] = {
        physical: p.stock_physical,
        reason: 'Stok Opname Rutin Toko',
        processing: false,
    };
});

const calculateDiff = (product) => {
    const input = opnameInputs.value[product.id]?.physical;
    if (input === undefined || input === null || input === '') return 0;
    return Number(input) - Number(product.stock_physical);
};

const submitSingleOpname = (product) => {
    const data = opnameInputs.value[product.id];
    if (!data) return;

    data.processing = true;
    router.post(`/products/${product.id}/adjust-stock`, {
        type: 'adjustment',
        qty: Number(data.physical),
        reason: data.reason || 'Stok Opname Rutin',
    }, {
        preserveScroll: true,
        onFinish: () => {
            data.processing = false;
        },
    });
};

// Brand Dropdown helpers
const selectedBrandName = computed(() => {
    const b = props.brands.find(item => item.id === addProductForm.brand_id);
    return b ? b.name : 'Pilih Merk (Opsional)...';
});

const filteredBrands = computed(() => {
    const q = brandSearchQuery.value.toLowerCase().trim();
    if (!q) return props.brands;
    return props.brands.filter(b => b.name.toLowerCase().includes(q));
});

const isBrandExactMatch = computed(() => {
    const q = brandSearchQuery.value.toLowerCase().trim();
    if (!q) return true;
    return props.brands.some(b => b.name.toLowerCase() === q);
});

const selectBrand = (brandId) => {
    addProductForm.brand_id = brandId;
    isBrandDropdownOpen.value = false;
    brandSearchQuery.value = '';
};

const createAndSelectBrand = () => {
    const brandName = brandSearchQuery.value.trim();
    if (!brandName) return;

    router.post('/brands', { name: brandName }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const newlyCreated = page.props.brands?.find(b => b.name.toLowerCase() === brandName.toLowerCase());
            if (newlyCreated) {
                addProductForm.brand_id = newlyCreated.id;
            }
            isBrandDropdownOpen.value = false;
            brandSearchQuery.value = '';
        },
    });
};

// Category Dropdown helpers
const selectedCategoryName = computed(() => {
    const c = props.categories.find(item => item.id === addProductForm.category_id);
    return c ? c.name : 'Pilih Kategori (Opsional)...';
});

const filteredCategories = computed(() => {
    const q = categorySearchQuery.value.toLowerCase().trim();
    if (!q) return props.categories;
    return props.categories.filter(c => c.name.toLowerCase().includes(q));
});

const isCategoryExactMatch = computed(() => {
    const q = categorySearchQuery.value.toLowerCase().trim();
    if (!q) return true;
    return props.categories.some(c => c.name.toLowerCase() === q);
});

const selectCategory = (catId) => {
    addProductForm.category_id = catId;
    isCategoryDropdownOpen.value = false;
    categorySearchQuery.value = '';
};

const createAndSelectCategory = () => {
    const catName = categorySearchQuery.value.trim();
    if (!catName) return;

    router.post('/categories', { name: catName }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const newlyCreated = page.props.categories?.find(c => c.name.toLowerCase() === catName.toLowerCase());
            if (newlyCreated) {
                addProductForm.category_id = newlyCreated.id;
            }
            isCategoryDropdownOpen.value = false;
            categorySearchQuery.value = '';
        },
    });
};

// Form & Modal Edit Produk
const isEditModalOpen = ref(false);
const editingProduct = ref(null);
const editProductForm = useForm({
    sku: '',
    barcode: '',
    name: '',
    category_id: null,
    brand_id: null,
    min_stock: 5,
    description: '',
    units: [],
});

const isEditBrandDropdownOpen = ref(false);
const editBrandSearchQuery = ref('');
const isEditCategoryDropdownOpen = ref(false);
const editCategorySearchQuery = ref('');

const selectedEditBrandName = computed(() => {
    const b = props.brands.find(item => item.id === editProductForm.brand_id);
    return b ? b.name : 'Pilih Merk...';
});

const filteredEditBrands = computed(() => {
    const q = editBrandSearchQuery.value.toLowerCase().trim();
    if (!q) return props.brands;
    return props.brands.filter(b => b.name.toLowerCase().includes(q));
});

const isEditBrandExactMatch = computed(() => {
    const q = editBrandSearchQuery.value.toLowerCase().trim();
    if (!q) return true;
    return props.brands.some(b => b.name.toLowerCase() === q);
});

const selectEditBrand = (brandId) => {
    editProductForm.brand_id = brandId;
    isEditBrandDropdownOpen.value = false;
    editBrandSearchQuery.value = '';
};

const createAndSelectEditBrand = () => {
    const brandName = editBrandSearchQuery.value.trim();
    if (!brandName) return;

    router.post('/brands', { name: brandName }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const newlyCreated = page.props.brands?.find(b => b.name.toLowerCase() === brandName.toLowerCase());
            if (newlyCreated) {
                editProductForm.brand_id = newlyCreated.id;
            }
            isEditBrandDropdownOpen.value = false;
            editBrandSearchQuery.value = '';
        },
    });
};

const selectedEditCategoryName = computed(() => {
    const c = props.categories.find(item => item.id === editProductForm.category_id);
    return c ? c.name : 'Pilih Kategori...';
});

const filteredEditCategories = computed(() => {
    const q = editCategorySearchQuery.value.toLowerCase().trim();
    if (!q) return props.categories;
    return props.categories.filter(c => c.name.toLowerCase().includes(q));
});

const isEditCategoryExactMatch = computed(() => {
    const q = editCategorySearchQuery.value.toLowerCase().trim();
    if (!q) return true;
    return props.categories.some(c => c.name.toLowerCase() === q);
});

const selectEditCategory = (catId) => {
    editProductForm.category_id = catId;
    isEditCategoryDropdownOpen.value = false;
    editCategorySearchQuery.value = '';
};

const createAndSelectEditCategory = () => {
    const catName = editCategorySearchQuery.value.trim();
    if (!catName) return;

    router.post('/categories', { name: catName }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const newlyCreated = page.props.categories?.find(c => c.name.toLowerCase() === catName.toLowerCase());
            if (newlyCreated) {
                editProductForm.category_id = newlyCreated.id;
            }
            isEditCategoryDropdownOpen.value = false;
            editCategorySearchQuery.value = '';
        },
    });
};

const openEditModal = (product) => {
    editingProduct.value = product;
    editProductForm.sku = product.sku;
    editProductForm.barcode = product.barcode || '';
    editProductForm.name = product.name;
    editProductForm.category_id = product.category_id;
    editProductForm.brand_id = product.brand_id;
    editProductForm.min_stock = product.min_stock;
    editProductForm.description = product.description || '';
    editProductForm.units = product.units.map(u => ({
        id: u.id,
        unit_name: u.unit_name,
        conversion_ratio: Number(u.conversion_ratio),
        cost_price: Number(u.cost_price),
        price_retail: Number(u.price_retail),
        is_base_unit: Boolean(u.is_base_unit),
    }));
    isEditModalOpen.value = true;
};

const addEditUnitRow = () => {
    editProductForm.units.push({
        unit_name: 'Dus',
        conversion_ratio: 12,
        cost_price: 0,
        price_retail: 0,
        is_base_unit: false,
    });
};

const removeEditUnitRow = (index) => {
    if (editProductForm.units.length > 1) {
        editProductForm.units.splice(index, 1);
    }
};

const submitEditProduct = () => {
    if (!editingProduct.value) return;
    editProductForm.put(`/products/${editingProduct.value.id}`, {
        onSuccess: () => {
            isEditModalOpen.value = false;
        },
    });
};

const deleteProduct = (product) => {
    if (!product) return;
    if (confirm(`Apakah Anda yakin ingin menghapus produk "${product.name}"?`)) {
        router.delete(`/products/${product.id}`, {
            onSuccess: () => {
                isEditModalOpen.value = false;
            },
        });
    }
};

const exportExcel = () => {
    window.location.href = '/products/export-excel';
};

const formatRupiahClean = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const printPriceList = () => {
    const listToPrint = filteredProducts.value;
    if (!listToPrint || listToPrint.length === 0) {
        alert('Tidak ada data produk yang dapat dicetak.');
        return;
    }

    let iframe = document.getElementById('pricelist-print-iframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'pricelist-print-iframe';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '300px';
        iframe.style.height = '300px';
        iframe.style.border = '0';
        iframe.style.opacity = '0';
        iframe.style.pointerEvents = 'none';
        iframe.style.zIndex = '-9999';
        document.body.appendChild(iframe);
    }

    let rowsHtml = '';
    let no = 1;
    listToPrint.forEach((p) => {
        const catName = p.category ? p.category.name : '-';
        const brandName = p.brand ? p.brand.name : '-';
        const units = p.units || [];

        if (units.length === 0) {
            rowsHtml += `
                <tr>
                    <td style="text-align:center; padding: 4px 6px;">${no++}</td>
                    <td style="font-family: monospace; padding: 4px 6px;">${p.barcode || p.sku || '-'}</td>
                    <td style="font-weight: bold; padding: 4px 6px;">${p.name}</td>
                    <td style="padding: 4px 6px;">${catName} / ${brandName}</td>
                    <td style="text-align:center; font-weight: bold; padding: 4px 6px;">${p.stock_available}</td>
                    <td style="text-align:center; padding: 4px 6px;">Pcs</td>
                    <td style="text-align:right; font-weight:bold; padding: 4px 6px;">Rp 0</td>
                    <td style="text-align:right; padding: 4px 6px;">Rp 0</td>
                    <td style="text-align:right; padding: 4px 6px;">Rp 0</td>
                    <td style="text-align:right; font-weight:bold; padding: 4px 6px;">Rp 0</td>
                </tr>
            `;
        } else {
            units.forEach((u, uIdx) => {
                rowsHtml += `
                    <tr style="border-bottom: 1px solid #cbd5e1;">
                `;
                if (uIdx === 0) {
                    const rspan = units.length;
                    rowsHtml += `
                        <td rowspan="${rspan}" style="text-align:center; vertical-align:middle; padding: 4px 6px; border-right: 1px solid #cbd5e1;">${no++}</td>
                        <td rowspan="${rspan}" style="font-family: monospace; font-size: 8pt; vertical-align:middle; padding: 4px 6px; border-right: 1px solid #cbd5e1;">${p.barcode || p.sku || '-'}</td>
                        <td rowspan="${rspan}" style="font-weight: bold; vertical-align:middle; padding: 4px 6px; border-right: 1px solid #cbd5e1;">${p.name}</td>
                        <td rowspan="${rspan}" style="font-size: 8pt; color: #475569; vertical-align:middle; padding: 4px 6px; border-right: 1px solid #cbd5e1;">${catName} &bull; ${brandName}</td>
                        <td rowspan="${rspan}" style="text-align:center; font-weight: 900; vertical-align:middle; padding: 4px 6px; border-right: 1px solid #cbd5e1;">${p.stock_available}</td>
                    `;
                }
                rowsHtml += `
                    <td style="text-align:center; font-weight:bold; padding: 4px 6px; border-right: 1px solid #cbd5e1;">${u.unit_name}${u.is_base_unit ? '' : ' (' + u.conversion_ratio + ')'}</td>
                    <td style="text-align:right; font-weight:bold; padding: 4px 6px;">${formatRupiahClean(u.price_retail)}</td>
                </tr>
                `;
            });
        }
    });

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>Katalog & Daftar Harga Produk</title>
            <style>
                @page {
                    size: A4 landscape;
                    margin: 8mm;
                }
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                body {
                    font-family: Arial, "Segoe UI", sans-serif;
                    font-size: 8.5pt;
                    color: #0f172a;
                    padding: 4px;
                    line-height: 1.2;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                }
                th {
                    background-color: #0f172a;
                    color: #ffffff;
                    font-weight: bold;
                    text-transform: uppercase;
                    font-size: 8pt;
                    padding: 6px 4px;
                    border: 1px solid #0f172a;
                }
                td {
                    border: 1px solid #cbd5e1;
                    font-size: 8pt;
                }
                tr:nth-child(even) {
                    background-color: #f8fafc;
                }
            </style>
        </head>
        <body>
            <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 8px;">
                <div>
                    <h1 style="font-size: 14pt; font-weight: 900; text-transform: uppercase; color: #0f172a; letter-spacing: 0.5px;">KANTIN RSIA AISYIYAH PEKAJANGAN</h1>
                    <p style="font-size: 9pt; font-weight: bold; color: #d97706;">KATALOG & DAFTAR HARGA BARANG (PRICE LIST RESMI)</p>
                    <p style="font-size: 7.5pt; color: #64748b;">Dokumen Backup Darurat Saat Sistem Offline</p>
                </div>
                <div style="text-align: right; font-size: 8pt;">
                    <div>Tanggal Cetak: <strong>${new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</strong></div>
                    <div>Total Produk: <strong>${listToPrint.length} Barang</strong></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 28px; text-align: center;">No</th>
                        <th style="width: 105px; text-align: left;">Barcode / SKU</th>
                        <th style="width: 240px; text-align: left;">Nama Produk</th>
                        <th style="width: 130px; text-align: left;">Kategori & Merk</th>
                        <th style="width: 48px; text-align: center;">Stok</th>
                        <th style="width: 80px; text-align: center;">Satuan</th>
                        <th style="width: 120px; text-align: right;">Harga Jual</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>
        </body>
        </html>
    `);
    doc.close();

    setTimeout(() => {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    }, 400);
};

const openAddProductModal = () => {
    addProductForm.reset();
    autoGenerateSku();
    autoGenerateBarcode();
    isAddModalOpen.value = true;
};

const autoGenerateSku = () => {
    const count = (props.products?.length || 0) + 1;
    let nextNum = count;
    let sku = 'ELK-' + String(nextNum).padStart(4, '0');
    while (props.products?.some(p => p.sku === sku)) {
        nextNum++;
        sku = 'ELK-' + String(nextNum).padStart(4, '0');
    }
    addProductForm.sku = sku;
};

const autoGenerateBarcode = () => {
    const maxId = (props.products || []).reduce((max, p) => Math.max(max, p.id || 0), 0);
    let nextId = maxId + 1;
    let barcode = '20' + String(nextId).padStart(6, '0');
    while (props.products?.some(p => p.barcode === barcode)) {
        nextId++;
        barcode = '20' + String(nextId).padStart(6, '0');
    }
    addProductForm.barcode = barcode;
};

const autoGenerateEditBarcode = () => {
    const maxId = (props.products || []).reduce((max, p) => Math.max(max, p.id || 0), 0);
    let nextId = editProductForm.id || (maxId + 1);
    let barcode = '20' + String(nextId).padStart(6, '0');
    while (props.products?.some(p => p.barcode === barcode && p.id !== editProductForm.id)) {
        nextId++;
        barcode = '20' + String(nextId).padStart(6, '0');
    }
    editProductForm.barcode = barcode;
};



const addUnitRow = () => {
    addProductForm.units.push({
        unit_name: 'Dus',
        conversion_ratio: 12,
        cost_price: 0,
        price_retail: 0,
        is_base_unit: false,
    });
};

const removeUnitRow = (index) => {
    if (addProductForm.units.length > 1) {
        addProductForm.units.splice(index, 1);
    }
};

const submitAddProduct = () => {
    addProductForm.post('/products', {
        onSuccess: () => {
            isAddModalOpen.value = false;
            addProductForm.reset();
        },
    });
};

// Form Penyesuaian Stok Cepat Modal
const adjustForm = useForm({
    type: 'in',
    qty: 1,
    reason: 'Pembelian dari Distributor',
});

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const filteredProducts = computed(() => {
    return props.products.filter(p => {
        const matchesCategory = opnameCategoryFilter.value === 'all' || p.category_id === Number(opnameCategoryFilter.value);
        const q = searchQuery.value.toLowerCase().trim();
        const matchesSearch = !q || 
            p.name.toLowerCase().includes(q) || 
            p.sku.toLowerCase().includes(q) || 
            (p.barcode && p.barcode.includes(q)) ||
            (p.brand && p.brand.name.toLowerCase().includes(q));
        return matchesCategory && matchesSearch;
    });
});

const openStockAdjustment = (product) => {
    selectedProduct.value = product;
    isAdjustModalOpen.value = true;
};

const submitAdjust = () => {
    adjustForm.post(`/products/${selectedProduct.value.id}/adjust-stock`, {
        onSuccess: () => {
            isAdjustModalOpen.value = false;
        },
    });
};

// Open Barcode Print Modal
const openBarcodeModal = (product) => {
    barcodeProduct.value = product;
    barcodeSelectedUnit.value = product.units.find(u => u.is_base_unit) || product.units[0];
    isBarcodeModalOpen.value = true;
    renderBarcodes();
};

const barcodeDataUrl = ref('');

const generateBarcodePng = (code, paperType) => {
    const canvas = document.createElement('canvas');
    const isMini = paperType === 'thermal_33x19';
    const isMedium = paperType === 'thermal_40x30';
    const len = (code || '').length;

    // Lebar batang proporsional & tajam pada resolusi thermal 203 DPI
    let barWidth;
    if (isMini) {
        barWidth = len > 13 ? 1.5 : (len > 9 ? 1.8 : 2.0);
    } else if (isMedium) {
        barWidth = len > 15 ? 1.8 : (len > 11 ? 2.2 : 2.5);
    } else {
        // 50x30 or A4
        barWidth = len > 16 ? 2.0 : (len > 12 ? 2.4 : 2.8);
    }

    const barHeight = isMini ? 40 : (isMedium ? 60 : 75);
    const fontSize = isMini ? 10 : (isMedium ? 15 : 16);
    const margin = isMini ? 6 : 10;

    try {
        JsBarcode(canvas, code, {
            format: 'CODE128',
            lineColor: '#000000',
            width: barWidth,
            height: barHeight,
            displayValue: !isMini,
            fontSize: fontSize,
            textMargin: 4,
            fontOptions: 'bold',
            font: 'monospace',
            margin: margin,
            flat: true,
        });
        return canvas.toDataURL('image/png');
    } catch (e) {
        console.error('Barcode Canvas Gen Error:', e);
        return '';
    }
};

const renderBarcodes = () => {
    nextTick(() => {
        const codeValue = (barcodeProduct.value?.barcode || barcodeProduct.value?.sku || '12345678').trim();
        barcodeDataUrl.value = generateBarcodePng(codeValue, barcodePaperType.value);
    });
};

const setPrintCount = (count) => {
    barcodePrintCount.value = count;
};

const printBarcodeStickers = () => {
    const product = barcodeProduct.value;
    if (!product) return;

    const unit = barcodeSelectedUnit.value;
    const priceText = formatRupiah(unit?.price_retail);
    const codeValue = (product.barcode || product.sku || '12345678').trim();
    const brandName = product.brand?.name || 'LISTRIK';
    const productName = product.name;
    const unitName = unit?.unit_name || 'Pcs';
    const count = Math.min(Math.max(Number(barcodePrintCount.value) || 1, 1), 100);

    const isMini = barcodePaperType.value === 'thermal_33x19';
    const isMedium = barcodePaperType.value === 'thermal_40x30';
    const isLarge = barcodePaperType.value === 'thermal_50x30';
    const isGridA4 = barcodePaperType.value === 'grid_a4';

    // Generate high-definition dedicated PNG image directly for print
    const imgDataUrl = generateBarcodePng(codeValue, barcodePaperType.value);
    const barcodeImgHtml = `<img src="${imgDataUrl}" class="barcode-img" alt="${codeValue}" />`;

    let itemsHtml = '';
    for (let i = 0; i < count; i++) {
        if (isMini) {
            itemsHtml += `
                <div class="sticker-33x19">
                    <div class="top-row">
                        <span class="pname">${productName}</span>
                    </div>
                    <div class="barcode-wrap">
                        ${barcodeImgHtml}
                    </div>
                    <div class="bottom-row">
                        <span class="psku">${codeValue}</span>
                        <span class="pprice">${priceText}</span>
                    </div>
                </div>
            `;
        } else if (isMedium || isLarge) {
            const cls = isMedium ? 'sticker-40x30' : 'sticker-50x30';
            itemsHtml += `
                <div class="${cls}">
                    <div class="header-row">
                        <span class="store-name">TRISNA JAYA</span>
                        <span class="pbrand">${brandName}</span>
                    </div>
                    <div class="pname-std">${productName}</div>
                    <div class="barcode-wrap-std">
                        ${barcodeImgHtml}
                    </div>
                    <div class="footer-row">
                        <span class="unit-label">1 ${unitName}:</span>
                        <span class="pprice-std">${priceText}</span>
                    </div>
                </div>
            `;
        } else {
            itemsHtml += `
                <div class="sticker-a4">
                    <div class="header-row">
                        <span class="store-name">TRISNA JAYA</span>
                        <span class="pbrand">${brandName}</span>
                    </div>
                    <div class="pname-std">${productName}</div>
                    <div class="barcode-wrap-std">
                        ${barcodeImgHtml}
                    </div>
                    <div class="footer-row">
                        <span class="unit-label">1 ${unitName}:</span>
                        <span class="pprice-std">${priceText}</span>
                    </div>
                </div>
            `;
        }
    }

    let iframe = document.getElementById('barcode-print-iframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'barcode-print-iframe';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '300px';
        iframe.style.height = '300px';
        iframe.style.border = '0';
        iframe.style.opacity = '0';
        iframe.style.pointerEvents = 'none';
        iframe.style.zIndex = '-9999';
        document.body.appendChild(iframe);
    }

    const pageSize = isMini ? '33mm 19mm' : (isMedium ? '40mm 30mm' : (isLarge ? '50mm 30mm' : 'A4 portrait'));

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>Cetak Barcode</title>
            <style>
                @page {
                    margin: 0;
                    size: ${pageSize};
                }
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                html, body {
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #ffffff !important;
                    color: #000000 !important;
                    font-family: Arial, Helvetica, sans-serif !important;
                    width: ${isMini ? '33mm' : (isMedium ? '40mm' : (isLarge ? '50mm' : '100%'))} !important;
                }
                #print-container {
                    ${isGridA4 ? 'display: grid; grid-template-columns: repeat(4, 45mm); gap: 4mm; justify-content: center; padding: 6mm;' : 'display: block; width: 100%; margin: 0 auto;'}
                }
                .sticker-33x19 {
                    width: 33mm !important;
                    height: 19mm !important;
                    max-height: 19mm !important;
                    padding: 0.8mm 1.5mm 0.8mm 2.5mm !important;
                    display: flex !important;
                    flex-direction: column !important;
                    justify-content: space-between !important;
                    align-items: center !important;
                    page-break-after: always !important;
                    break-after: page !important;
                    page-break-inside: avoid !important;
                    break-inside: avoid !important;
                    box-sizing: border-box !important;
                    overflow: hidden !important;
                    background: #ffffff !important;
                }
                .sticker-33x19:last-child {
                    page-break-after: avoid !important;
                    break-after: avoid !important;
                }
                .sticker-33x19 .top-row {
                    width: 100% !important;
                    display: flex !important;
                    justify-content: center !important;
                    align-items: center !important;
                    text-align: center !important;
                }
                .sticker-33x19 .top-row .pname {
                    width: 100% !important;
                    font-size: 5pt !important;
                    font-weight: 900 !important;
                    line-height: 1.2 !important;
                    text-align: center !important;
                    text-transform: uppercase !important;
                    color: #000000 !important;
                    display: -webkit-box !important;
                    -webkit-box-orient: vertical !important;
                    -webkit-line-clamp: 2 !important;
                    overflow: hidden !important;
                    white-space: normal !important;
                    word-break: break-word !important;
                }
                .sticker-33x19 .barcode-wrap {
                    width: 100% !important;
                    display: flex !important;
                    justify-content: center !important;
                    align-items: center !important;
                    overflow: hidden !important;
                    margin: auto 0 !important;
                }
                .barcode-img {
                    image-rendering: -webkit-optimize-contrast !important;
                    image-rendering: pixelated !important;
                    image-rendering: crisp-edges !important;
                    display: block !important;
                    margin: 0 auto !important;
                }
                .sticker-33x19 .barcode-wrap .barcode-img {
                    max-height: 8.5mm !important;
                    height: 8.5mm !important;
                    width: auto !important;
                    max-width: 30mm !important;
                }
                .sticker-33x19 .bottom-row {
                    width: 100% !important;
                    display: flex !important;
                    justify-content: space-between !important;
                    align-items: center !important;
                    font-size: 5pt !important;
                    line-height: 1 !important;
                    border-top: 0.5px solid #cbd5e1 !important;
                    padding-top: 0.5mm !important;
                }
                .sticker-33x19 .bottom-row .psku {
                    font-family: monospace !important;
                    font-size: 5pt !important;
                    font-weight: bold !important;
                    color: #000000 !important;
                    max-width: 50% !important;
                    white-space: nowrap !important;
                    overflow: hidden !important;
                    text-overflow: ellipsis !important;
                }
                .sticker-33x19 .bottom-row .pprice {
                    font-size: 6.5pt !important;
                    font-weight: 900 !important;
                    color: #000000 !important;
                    max-width: 55% !important;
                    white-space: nowrap !important;
                    overflow: hidden !important;
                    text-overflow: ellipsis !important;
                }

                .sticker-40x30 {
                    width: 40mm !important;
                    height: 30mm !important;
                    max-height: 30mm !important;
                    padding: 1.2mm 1.5mm !important;
                    display: flex !important;
                    flex-direction: column !important;
                    justify-content: space-between !important;
                    align-items: center !important;
                    text-align: center !important;
                    page-break-after: always !important;
                    break-after: page !important;
                    page-break-inside: avoid !important;
                    break-inside: avoid !important;
                    box-sizing: border-box !important;
                    overflow: hidden !important;
                }
                .sticker-40x30:last-child {
                    page-break-after: avoid !important;
                    break-after: avoid !important;
                }
                .sticker-50x30 {
                    width: 50mm !important;
                    height: 30mm !important;
                    max-height: 30mm !important;
                    padding: 1.2mm 2mm !important;
                    display: flex !important;
                    flex-direction: column !important;
                    justify-content: space-between !important;
                    align-items: center !important;
                    text-align: center !important;
                    page-break-after: always !important;
                    break-after: page !important;
                    page-break-inside: avoid !important;
                    break-inside: avoid !important;
                    box-sizing: border-box !important;
                    overflow: hidden !important;
                }
                .sticker-50x30:last-child {
                    page-break-after: avoid !important;
                    break-after: avoid !important;
                }
                .sticker-a4 {
                    width: 45mm !important;
                    height: 28mm !important;
                    max-height: 28mm !important;
                    padding: 1.5mm !important;
                    border: 1px dashed #94a3b8 !important;
                    border-radius: 4px !important;
                    display: flex !important;
                    flex-direction: column !important;
                    justify-content: space-between !important;
                    align-items: center !important;
                    text-align: center !important;
                    page-break-inside: avoid !important;
                    break-inside: avoid !important;
                    box-sizing: border-box !important;
                    overflow: hidden !important;
                }
                .header-row {
                    width: 100% !important;
                    display: flex !important;
                    justify-content: space-between !important;
                    align-items: center !important;
                    border-bottom: 0.5px solid #e2e8f0 !important;
                    padding-bottom: 0.5mm !important;
                }
                .header-row .store-name {
                    font-size: 7.5pt !important;
                    font-weight: 900 !important;
                    text-transform: uppercase !important;
                    color: #0f172a !important;
                }
                .header-row .pbrand {
                    font-size: 7pt !important;
                    font-weight: bold !important;
                    text-transform: uppercase !important;
                    color: #b45309 !important;
                }
                .pname-std {
                    font-size: 7pt !important;
                    font-weight: 900 !important;
                    line-height: 1.2 !important;
                    color: #000000 !important;
                    margin: 0.5mm 0 !important;
                    overflow: hidden !important;
                    display: -webkit-box !important;
                    -webkit-box-orient: vertical !important;
                    -webkit-line-clamp: 2 !important;
                    white-space: normal !important;
                    word-break: break-word !important;
                    width: 100% !important;
                    text-align: center !important;
                }
                .barcode-wrap-std {
                    width: 100% !important;
                    flex: 1 !important;
                    display: flex !important;
                    justify-content: center !important;
                    align-items: center !important;
                    overflow: hidden !important;
                    margin: 0.3mm 0 !important;
                }
                .barcode-wrap-std .barcode-img {
                    max-height: 13mm !important;
                    height: 13mm !important;
                    width: auto !important;
                    max-width: 47mm !important;
                }
                .footer-row {
                    width: 100% !important;
                    display: flex !important;
                    justify-content: space-between !important;
                    align-items: center !important;
                    border-top: 0.5px solid #e2e8f0 !important;
                    padding-top: 0.5mm !important;
                }
                .footer-row .unit-label {
                    font-size: 7pt !important;
                    color: #64748b !important;
                    font-weight: 600 !important;
                }
                .footer-row .pprice-std {
                    font-size: 9pt !important;
                    font-weight: 900 !important;
                    color: #000000 !important;
                }
            </style>
        </head>
        <body>
            <div id="print-container">
                ${itemsHtml}
            </div>
        </body>
        </html>
    `);
    doc.close();

    setTimeout(() => {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (err) {
            console.error('Iframe print error:', err);
            window.print();
        }
    }, 250);
};

// ==========================================
// ====================================================
// MASTER KATEGORI, MERK & SATUAN (UNITS) MANAGEMENT
// ====================================================
const isMasterCategoryBrandModalOpen = ref(false);
const masterTab = ref('categories'); // 'categories', 'brands', or 'units'
const masterCategorySearch = ref('');
const masterBrandSearch = ref('');
const masterUnitSearch = ref('');

// Category inline editing & adding
const editingCategoryId = ref(null);
const editCategoryNameInput = ref('');
const newCategoryNameInput = ref('');
const isCreatingCategory = ref(false);

// Brand inline editing & adding
const editingBrandId = ref(null);
const editBrandNameInput = ref('');
const newBrandNameInput = ref('');
const isCreatingBrand = ref(false);

// Unit inline editing & adding
const editingUnitId = ref(null);
const editUnitNameInput = ref('');
const newUnitNameInput = ref('');
const isCreatingUnit = ref(false);

const filteredMasterCategories = computed(() => {
    const q = masterCategorySearch.value.toLowerCase().trim();
    if (!props.categories) return [];
    if (!q) return props.categories;
    return props.categories.filter(c => c.name.toLowerCase().includes(q));
});

const filteredMasterBrands = computed(() => {
    const q = masterBrandSearch.value.toLowerCase().trim();
    if (!props.brands) return [];
    if (!q) return props.brands;
    return props.brands.filter(b => b.name.toLowerCase().includes(q));
});

const filteredMasterUnits = computed(() => {
    const q = masterUnitSearch.value.toLowerCase().trim();
    if (!props.units) return [];
    if (!q) return props.units;
    return props.units.filter(u => u.name.toLowerCase().includes(q));
});

const startEditCategory = (cat, e) => {
    if (e) e.stopPropagation();
    editingCategoryId.value = cat.id;
    editCategoryNameInput.value = cat.name;
};

const cancelEditCategory = (e) => {
    if (e) e.stopPropagation();
    editingCategoryId.value = null;
    editCategoryNameInput.value = '';
};

const saveEditCategory = (cat, e) => {
    if (e) e.stopPropagation();
    const name = editCategoryNameInput.value.trim();
    if (!name || name === cat.name) {
        editingCategoryId.value = null;
        return;
    }
    router.put(`/categories/${cat.id}`, { name }, {
        preserveScroll: true,
        onSuccess: () => {
            editingCategoryId.value = null;
            editCategoryNameInput.value = '';
        }
    });
};

const deleteCategory = (cat, e) => {
    if (e) e.stopPropagation();
    const count = cat.products_count || 0;
    const countMsg = count > 0 ? `PERINGATAN: Terdapat ${count} produk yang saat ini menggunakan kategori ini. Jika dihapus, kategori produk tersebut akan menjadi kosong (Tanpa Kategori).` : '';
    if (confirm(`Apakah Anda yakin ingin menghapus kategori "${cat.name}"?\n\n${countMsg}`)) {
        router.delete(`/categories/${cat.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (addProductForm.category_id === cat.id) addProductForm.category_id = null;
                if (editProductForm.category_id === cat.id) editProductForm.category_id = null;
            }
        });
    }
};

const submitNewCategory = () => {
    const name = newCategoryNameInput.value.trim();
    if (!name) return;
    isCreatingCategory.value = true;
    router.post('/categories', { name }, {
        preserveScroll: true,
        onSuccess: () => {
            newCategoryNameInput.value = '';
            isCreatingCategory.value = false;
        },
        onFinish: () => {
            isCreatingCategory.value = false;
        }
    });
};

const startEditBrand = (brand, e) => {
    if (e) e.stopPropagation();
    editingBrandId.value = brand.id;
    editBrandNameInput.value = brand.name;
};

const cancelEditBrand = (e) => {
    if (e) e.stopPropagation();
    editingBrandId.value = null;
    editBrandNameInput.value = '';
};

const saveEditBrand = (brand, e) => {
    if (e) e.stopPropagation();
    const name = editBrandNameInput.value.trim();
    if (!name || name === brand.name) {
        editingBrandId.value = null;
        return;
    }
    router.put(`/brands/${brand.id}`, { name }, {
        preserveScroll: true,
        onSuccess: () => {
            editingBrandId.value = null;
            editBrandNameInput.value = '';
        }
    });
};

const deleteBrand = (brand, e) => {
    if (e) e.stopPropagation();
    const count = brand.products_count || 0;
    const countMsg = count > 0 ? `PERINGATAN: Terdapat ${count} produk yang saat ini menggunakan merk ini. Jika dihapus, merk produk tersebut akan menjadi kosong (Tanpa Merk).` : '';
    if (confirm(`Apakah Anda yakin ingin menghapus merk "${brand.name}"?\n\n${countMsg}`)) {
        router.delete(`/brands/${brand.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (addProductForm.brand_id === brand.id) addProductForm.brand_id = null;
                if (editProductForm.brand_id === brand.id) editProductForm.brand_id = null;
            }
        });
    }
};

const submitNewBrand = () => {
    const name = newBrandNameInput.value.trim();
    if (!name) return;
    isCreatingBrand.value = true;
    router.post('/brands', { name }, {
        preserveScroll: true,
        onSuccess: () => {
            newBrandNameInput.value = '';
            isCreatingBrand.value = false;
        },
        onFinish: () => {
            isCreatingBrand.value = false;
        }
    });
};

// Unit Methods
const startEditUnit = (unit, e) => {
    if (e) e.stopPropagation();
    editingUnitId.value = unit.id;
    editUnitNameInput.value = unit.name;
};

const cancelEditUnit = (e) => {
    if (e) e.stopPropagation();
    editingUnitId.value = null;
    editUnitNameInput.value = '';
};

const saveEditUnit = (unit, e) => {
    if (e) e.stopPropagation();
    const name = editUnitNameInput.value.trim();
    if (!name || name === unit.name) {
        editingUnitId.value = null;
        return;
    }
    router.put(`/units/${unit.id}`, { name }, {
        preserveScroll: true,
        onSuccess: () => {
            editingUnitId.value = null;
            editUnitNameInput.value = '';
        }
    });
};

const deleteUnit = (unit, e) => {
    if (e) e.stopPropagation();
    const count = unit.product_units_count || 0;
    const countMsg = count > 0 ? `PERINGATAN: Terdapat ${count} item barang yang menggunakan satuan ini.` : '';
    if (confirm(`Apakah Anda yakin ingin menghapus master satuan "${unit.name}"?\n\n${countMsg}`)) {
        router.delete(`/units/${unit.id}`, {
            preserveScroll: true,
        });
    }
};

const submitNewUnit = () => {
    const name = newUnitNameInput.value.trim();
    if (!name) return;
    isCreatingUnit.value = true;
    router.post('/units', { name }, {
        preserveScroll: true,
        onSuccess: () => {
            newUnitNameInput.value = '';
            isCreatingUnit.value = false;
        },
        onFinish: () => {
            isCreatingUnit.value = false;
        }
    });
};
</script>

<template>
    <MainLayout>
        <Head title="Master Produk & Stok Opname" />
        <div class="p-6 w-full space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-black text-slate-900 flex items-center gap-2.5">
                        <Package class="w-6 h-6 text-amber-600" />
                        <span>Master Produk & Stok Opname</span>
                    </h1>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 mt-1">
                        <span>Katalog barang listrik, audit hitung fisik (Stok Opname), dan pencetakan stiker label barcode.</span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-slate-800 font-bold text-[11px]">
                            <Package class="w-3 h-3 text-amber-600" />
                            <span>Total Master: <strong>{{ products.length }}</strong> Barang</span>
                        </span>
                    </div>
                </div>

                <!-- Tabs Selector -->
                <div class="flex flex-wrap items-center gap-1.5 p-1 bg-white border border-slate-200 rounded-2xl shadow-xs">
                    <button 
                        @click="activeTab = 'catalog'"
                        :class="activeTab === 'catalog' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <Layers class="w-3.5 h-3.5" />
                        <span>Katalog Produk ({{ products.length }})</span>
                    </button>
                    <button 
                        v-if="user.role === 'admin' || user.role === 'gudang'"
                        @click="activeTab = 'opname'"
                        :class="activeTab === 'opname' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <ClipboardCheck class="w-3.5 h-3.5 text-amber-400" />
                        <span>Audit Stok Opname</span>
                    </button>
                    <button 
                        v-if="user.role === 'admin' || user.role === 'gudang'"
                        @click="activeTab = 'logs'"
                        :class="activeTab === 'logs' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <History class="w-3.5 h-3.5" />
                        <span>Riwayat Log Opname</span>
                    </button>
                    <button 
                        v-if="user.role === 'admin' || user.role === 'gudang'"
                        @click="activeTab = 'categories_brands'"
                        :class="activeTab === 'categories_brands' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <Tag class="w-3.5 h-3.5 text-amber-400" />
                        <span>Kategori, Merk & Satuan</span>
                    </button>
                </div>
            </div>

            <!-- TAB 1: KATALOG PRODUK -->
            <div v-if="activeTab === 'catalog'" class="space-y-4">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                    <!-- Search Input & Total Products Badge -->
                    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                        <div class="relative w-full sm:w-80">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                            <input 
                                v-model="searchQuery"
                                type="text" 
                                placeholder="Cari Barcode / SKU / Nama..."
                                class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500 shadow-xs"
                            />
                        </div>
                        <div class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-xl text-[11px] font-bold text-slate-700 shrink-0">
                            Total: <strong class="text-slate-900">{{ products.length }}</strong> Barang
                            <span v-if="searchQuery.trim()" class="text-amber-700 ml-1">
                                (Ditemukan {{ filteredProducts.length }})
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        <button 
                            @click="exportExcel"
                            class="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 transition shadow-xs shrink-0 cursor-pointer w-full sm:w-auto justify-center active:scale-95"
                            title="Download Data Barang & Harga Format Excel (.xls)"
                        >
                            <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
                            <span>Export Excel</span>
                        </button>

                        <button 
                            @click="printPriceList"
                            class="bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-300 font-bold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 transition shadow-xs shrink-0 cursor-pointer w-full sm:w-auto justify-center active:scale-95"
                            title="Cetak atau Simpan PDF Katalog & Price List Resmi"
                        >
                            <FileText class="w-4 h-4 text-rose-600" />
                            <span>Export PDF / Cetak</span>
                        </button>

                        <button 
                            v-if="user.role === 'admin' || user.role === 'gudang'"
                            @click="isMasterCategoryBrandModalOpen = true"
                            class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 transition shadow-xs shrink-0 cursor-pointer w-full sm:w-auto justify-center"
                        >
                            <Tag class="w-4 h-4 text-amber-500" />
                            <span>Kelola Master Data</span>
                        </button>

                        <button 
                            v-if="user.role === 'admin' || user.role === 'gudang'"
                            @click="openAddProductModal"
                            class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-2 transition shadow-xs shrink-0 cursor-pointer w-full sm:w-auto justify-center"
                        >
                            <PackagePlus class="w-4 h-4 text-amber-400" />
                            <span>Tambah Barang Baru</span>
                        </button>
                    </div>
                </div>

                <!-- Product Table with Sticky Header and Smooth Scroll -->
                <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs">
                    <div class="max-h-[calc(100vh-270px)] overflow-y-auto overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 z-10 bg-slate-50 border-b border-slate-200 shadow-xs">
                                <tr class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                    <th class="py-3.5 px-3.5 w-12 text-center bg-slate-50">No</th>
                                    <th class="py-3.5 px-4 bg-slate-50">Produk & Barcode</th>
                                    <th class="py-3.5 px-4 bg-slate-50">Kategori / Merk</th>
                                    <th class="py-3.5 px-4 text-center bg-slate-50">Status Stok</th>
                                    <th v-if="canSeeCostPrice" class="py-3.5 px-4 bg-slate-50">HPP (Modal)</th>
                                    <th class="py-3.5 px-4 bg-slate-50">Harga Jual</th>
                                    <th class="py-3.5 px-4 text-center bg-slate-50">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(product, index) in filteredProducts" :key="product.id" class="hover:bg-slate-50 transition">
                                    <td class="py-3.5 px-3.5 text-center text-slate-400 font-mono text-[11px] font-bold">
                                        {{ index + 1 }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900 text-xs">{{ product.name }}</div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-[10px] font-mono text-slate-400">{{ product.sku }}</span>
                                            <span v-if="product.barcode" class="text-[10px] font-mono text-amber-700 bg-amber-50 px-1.5 py-0.2 rounded border border-amber-200">
                                                ||| {{ product.barcode }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-bold">
                                            {{ product.category?.name || '-' }}
                                        </span>
                                        <div class="text-[10px] text-slate-400 mt-0.5 font-semibold">{{ product.brand?.name || '-' }}</div>
                                    </td>
                                    
                                    <!-- Stock Breakdown: Fisik, Booked SO, dan Sedia -->
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="inline-flex flex-col items-center space-y-1">
                                            <span 
                                                :class="product.stock_available <= product.min_stock ? 'text-rose-700 bg-rose-50 border border-rose-200' : 'text-emerald-700 bg-emerald-50 border border-emerald-200'"
                                                class="px-2.5 py-1 rounded-full text-xs font-black"
                                                title="Stok Siap Jual ke Kasir"
                                            >
                                                Sedia: {{ product.stock_available }} {{ product.units[0]?.unit_name }}
                                            </span>
                                            <div class="text-[10px] text-slate-500 font-medium flex items-center gap-1.5">
                                                <span>Fisik: <strong>{{ product.stock_physical }}</strong></span>
                                                <span v-if="product.stock_booked > 0" class="text-amber-600 font-bold">
                                                    (Booked: {{ product.stock_booked }})
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- HPP / Modal -->
                                    <td v-if="canSeeCostPrice" class="py-3.5 px-4 space-y-1 bg-slate-50/50">
                                        <div v-for="u in product.units" :key="u.id" class="text-[11px]">
                                            <span class="text-slate-400 font-semibold">{{ u.unit_name }}:</span>
                                            <span class="text-slate-700 font-bold ml-1">{{ formatRupiah(u.cost_price) }}</span>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4 space-y-1">
                                        <div v-for="u in product.units" :key="u.id" class="text-[11px]">
                                            <span class="text-slate-400 font-semibold">{{ u.unit_name }}:</span>
                                            <span class="text-slate-900 font-bold ml-1">{{ formatRupiah(u.price_retail) }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button 
                                                v-if="user.role === 'admin' || user.role === 'gudang'"
                                                @click="openEditModal(product)"
                                                class="px-2.5 py-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-900 border border-blue-200 font-bold text-[11px] flex items-center gap-1 transition cursor-pointer active:scale-95"
                                                title="Edit Data Produk & Harga"
                                            >
                                                <Edit3 class="w-3.5 h-3.5 text-blue-700" />
                                                <span>Edit</span>
                                            </button>
                                            <button 
                                                @click="openBarcodeModal(product)"
                                                class="px-2.5 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 font-bold text-[11px] flex items-center gap-1 transition cursor-pointer active:scale-95"
                                                title="Cetak Stiker Barcode & Harga"
                                            >
                                                <Barcode class="w-3.5 h-3.5 text-amber-700" />
                                                <span>Barcode</span>
                                            </button>
                                            <button 
                                                v-if="user.role === 'admin'"
                                                @click="openStockAdjustment(product)"
                                                class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] flex items-center gap-1 transition cursor-pointer active:scale-95"
                                                title="Penyesuaian Stok Fisik Darurat (Hanya Admin)"
                                            >
                                                <ArrowUpDown class="w-3.5 h-3.5 text-slate-900" />
                                                <span>Stok (Admin)</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: AUDIT STOK OPNAME KERJA TOKO -->
            <div v-if="activeTab === 'opname'" class="space-y-4">
                <div class="bg-amber-50 border border-amber-200 rounded-3xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-amber-200 text-amber-800 flex items-center justify-center font-black shrink-0">
                            <ClipboardCheck class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-amber-950">Lembar Kerja Audit Stok Opname (Hitung Fisik Aktual)</h3>
                            <p class="text-xs text-amber-800 mt-0.5">
                                Hitung seluruh fisik barang di toko/gudang. Stok Sistem di bawah ini adalah <strong>Stok Fisik Aktual Total</strong>.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-600">Filter Kategori:</span>
                        <select v-model="opnameCategoryFilter" class="bg-white border border-amber-300 rounded-xl px-3 py-1.5 text-xs text-slate-900 font-bold">
                            <option value="all">Semua Kategori</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs">
                    <div class="max-h-[calc(100vh-270px)] overflow-y-auto overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 z-10 bg-slate-50 border-b border-slate-200 shadow-xs">
                                <tr class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                    <th class="py-3.5 px-3.5 w-12 text-center bg-slate-50">No</th>
                                    <th class="py-3.5 px-4 bg-slate-50">Nama Produk Listrik</th>
                                    <th class="py-3.5 px-4 text-center bg-slate-50">Stok Fisik di Sistem</th>
                                    <th class="py-3.5 px-4 text-center bg-slate-50">Hitungan Fisik Nyata</th>
                                    <th class="py-3.5 px-4 text-center bg-slate-50">Selisih (+/-)</th>
                                    <th class="py-3.5 px-4 bg-slate-50">Alasan / Catatan Penyesuaian</th>
                                    <th class="py-3.5 px-4 text-center bg-slate-50">Simpan Opname</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(product, index) in filteredProducts" :key="product.id" class="hover:bg-slate-50 transition">
                                    <td class="py-3.5 px-3.5 text-center text-slate-400 font-mono text-[11px] font-bold">
                                        {{ index + 1 }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900 text-xs">{{ product.name }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ product.sku }} &bull; {{ product.brand?.name }}</div>
                                    </td>

                                    <td class="py-3.5 px-4 text-center">
                                        <div class="inline-flex flex-col items-center">
                                            <span class="px-2.5 py-1 rounded-xl bg-slate-100 text-slate-800 font-black text-xs">
                                                {{ product.stock_physical }} {{ product.units[0]?.unit_name }}
                                            </span>
                                            <span v-if="product.stock_booked > 0" class="text-[9px] text-amber-700 font-bold mt-0.5">
                                                (Di-booking SO: {{ product.stock_booked }})
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <input 
                                                v-if="opnameInputs[product.id]"
                                                v-model.number="opnameInputs[product.id].physical"
                                                type="number"
                                                step="0.1"
                                                min="0"
                                                class="w-20 bg-slate-50 border border-slate-300 focus:border-amber-500 rounded-xl px-2.5 py-1.5 text-center font-black text-xs text-slate-900"
                                            />
                                            <span class="text-[11px] text-slate-500 font-semibold">{{ product.units[0]?.unit_name }}</span>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4 text-center">
                                        <template v-if="calculateDiff(product) === 0">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-600">
                                                0 (Cocok)
                                            </span>
                                        </template>
                                        <template v-else-if="calculateDiff(product) > 0">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-800 border border-emerald-300">
                                                +{{ calculateDiff(product) }} (Lebih)
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-50 text-rose-800 border border-rose-300">
                                                {{ calculateDiff(product) }} (Kurang)
                                            </span>
                                        </template>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <input 
                                            v-if="opnameInputs[product.id]"
                                            v-model="opnameInputs[product.id].reason"
                                            type="text"
                                            placeholder="Alasan selisih..."
                                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1 text-xs text-slate-800"
                                        />
                                    </td>

                                    <td class="py-3.5 px-4 text-center">
                                        <button 
                                            @click="submitSingleOpname(product)"
                                            :disabled="opnameInputs[product.id]?.processing || calculateDiff(product) === 0"
                                            :class="calculateDiff(product) !== 0 ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-xs' : 'bg-slate-100 text-slate-400 cursor-not-allowed'"
                                            class="px-3 py-1.5 rounded-xl font-bold text-[11px] transition flex items-center gap-1 mx-auto cursor-pointer"
                                        >
                                            <Check class="w-3.5 h-3.5" />
                                            <span>Sesuaikan</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: RIWAYAT & LOG AUDIT STOK OPNAME -->
            <div v-if="activeTab === 'logs'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-black text-slate-900 mb-0.5">Riwayat Log Penyesuaian & Opname Stok</h3>
                            <p class="text-xs text-slate-500">Catatan lengkap audit stok opname, mutasi barang masuk/keluar, dan nama staf penanggung jawab.</p>
                        </div>
                        <span class="px-3 py-1 bg-slate-100 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                            Total: {{ stockLogs.length }} Aktivitas Log
                        </span>
                    </div>

                    <div class="max-h-[calc(100vh-320px)] overflow-y-auto overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 z-10 bg-slate-50 border-b border-slate-200 shadow-xs">
                                <tr class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                    <th class="py-3 px-3 w-12 text-center bg-slate-50">No</th>
                                    <th class="py-3 px-3 bg-slate-50">Tanggal & Waktu</th>
                                    <th class="py-3 px-3 bg-slate-50">Nama Produk</th>
                                    <th class="py-3 px-3 bg-slate-50">Petugas Audit</th>
                                    <th class="py-3 px-3 text-center bg-slate-50">Tipe Mutasi</th>
                                    <th class="py-3 px-3 text-center bg-slate-50">Selisih Qty</th>
                                    <th class="py-3 px-3 bg-slate-50">Alasan / Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(log, index) in stockLogs" :key="log.id" class="hover:bg-slate-50">
                                    <td class="py-3 px-3 text-center text-slate-400 font-mono text-[11px] font-bold">
                                        {{ index + 1 }}
                                    </td>
                                    <td class="py-3 px-3 text-slate-500 font-mono text-[11px]">
                                        {{ new Date(log.created_at).toLocaleString('id-ID') }}
                                    </td>
                                    <td class="py-3 px-3 font-bold text-slate-900">
                                        {{ log.product?.name }}
                                    </td>
                                    <td class="py-3 px-3 text-slate-700 font-semibold">
                                        {{ log.user?.name }} ({{ log.user?.role }})
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span v-if="log.type === 'adjustment'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-800 border border-purple-200">
                                            Stok Opname
                                        </span>
                                        <span v-else-if="log.type === 'in'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            Barang Masuk (+)
                                        </span>
                                        <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                            Barang Keluar (-)
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center font-black text-xs">
                                        <span :class="log.qty_change >= 0 ? 'text-emerald-700' : 'text-rose-600'">
                                            {{ log.qty_change >= 0 ? '+' : '' }}{{ log.qty_change }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-slate-600 italic">
                                        "{{ log.reason }}"
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: MASTER KATEGORI, MERK & SATUAN (UNITS) -->
            <div v-if="activeTab === 'categories_brands'" class="space-y-6">
                <!-- Summary Header -->
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center font-black shrink-0">
                            <Tag class="w-5 h-5" />
                        </div>
                        <div>
                            <h2 class="text-sm font-black text-slate-900">Kelola Master Kategori, Merk & Satuan</h2>
                            <p class="text-xs text-slate-500">Edit nama, perbaiki typo/duplikat, atau hapus kategori, merk, dan satuan produk listrik.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5 text-xs font-bold">
                        <div class="px-3.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700">
                            Kategori: <strong class="text-slate-900">{{ categories.length }}</strong>
                        </div>
                        <div class="px-3.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700">
                            Merk: <strong class="text-slate-900">{{ brands.length }}</strong>
                        </div>
                        <div class="px-3.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700">
                            Satuan: <strong class="text-slate-900">{{ (units || []).length }}</strong>
                        </div>
                    </div>
                </div>

                <!-- 3 Columns Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- KOLOM 1: MASTER KATEGORI -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4 flex flex-col">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <Shapes class="w-4 h-4 text-amber-600" />
                                <h3 class="text-sm font-black text-slate-900">Master Kategori</h3>
                            </div>
                            <span class="text-[11px] font-bold text-slate-400">{{ filteredMasterCategories.length }} Item</span>
                        </div>

                        <!-- Form Tambah Kategori -->
                        <form @submit.prevent="submitNewCategory" class="flex gap-2">
                            <input 
                                v-model="newCategoryNameInput"
                                type="text"
                                required
                                placeholder="Nama kategori baru..."
                                class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-900 font-semibold focus:outline-none focus:border-amber-500 focus:bg-white transition"
                            />
                            <button 
                                type="submit"
                                :disabled="isCreatingCategory || !newCategoryNameInput.trim()"
                                class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-1 shrink-0 transition disabled:opacity-50 cursor-pointer shadow-xs"
                            >
                                <Plus class="w-3.5 h-3.5 text-amber-400" />
                                <span>{{ isCreatingCategory ? '...' : 'Tambah' }}</span>
                            </button>
                        </form>

                        <!-- Search Kategori -->
                        <div class="relative">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input 
                                v-model="masterCategorySearch"
                                type="text"
                                placeholder="Cari nama kategori..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                            />
                        </div>

                        <!-- Table List Kategori -->
                        <div class="border border-slate-200 rounded-2xl overflow-hidden flex-1 max-h-[460px] overflow-y-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
                                    <tr class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                        <th class="py-2.5 px-3 w-10 text-center">No</th>
                                        <th class="py-2.5 px-3">Nama Kategori</th>
                                        <th class="py-2.5 px-2 text-center w-20">Produk</th>
                                        <th class="py-2.5 px-2 text-right w-20">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-if="filteredMasterCategories.length === 0">
                                        <td colspan="4" class="py-6 text-center text-slate-400 text-xs">
                                            Tidak ada kategori.
                                        </td>
                                    </tr>
                                    <tr 
                                        v-for="(cat, idx) in filteredMasterCategories" 
                                        :key="cat.id"
                                        class="hover:bg-slate-50/80 transition"
                                    >
                                        <td class="py-2.5 px-3 text-center text-slate-400 font-mono text-[11px]">
                                            {{ idx + 1 }}
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <!-- Inline Edit Mode -->
                                            <div v-if="editingCategoryId === cat.id" class="flex items-center gap-1" @click.stop>
                                                <input 
                                                    v-model="editCategoryNameInput"
                                                    type="text"
                                                    class="flex-1 bg-white border border-amber-400 rounded px-2 py-0.5 text-xs text-slate-900 font-bold focus:outline-none ring-1 ring-amber-300"
                                                    @keyup.enter="saveEditCategory(cat)"
                                                    @keyup.esc="cancelEditCategory"
                                                    autofocus
                                                />
                                                <button 
                                                    @click="saveEditCategory(cat)"
                                                    type="button"
                                                    class="p-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded cursor-pointer"
                                                    title="Simpan"
                                                >
                                                    <Check class="w-3 h-3" />
                                                </button>
                                                <button 
                                                    @click="cancelEditCategory"
                                                    type="button"
                                                    class="p-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded cursor-pointer"
                                                    title="Batal"
                                                >
                                                    <X class="w-3 h-3" />
                                                </button>
                                            </div>
                                            <!-- Normal Display -->
                                            <div v-else class="font-bold text-slate-900 truncate max-w-[140px]" :title="cat.name">
                                                {{ cat.name }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-2 text-center">
                                            <span class="px-1.5 py-0.5 bg-slate-100 text-slate-700 rounded text-[10px] font-bold">
                                                {{ cat.products_count || 0 }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-2 text-right">
                                            <div v-if="editingCategoryId !== cat.id" class="flex items-center justify-end gap-0.5">
                                                <button 
                                                    @click="startEditCategory(cat, $event)"
                                                    type="button"
                                                    class="p-1 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded transition cursor-pointer"
                                                    title="Edit Nama Kategori"
                                                >
                                                    <Pencil class="w-3.5 h-3.5" />
                                                </button>
                                                <button 
                                                    @click="deleteCategory(cat, $event)"
                                                    type="button"
                                                    class="p-1 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded transition cursor-pointer"
                                                    title="Hapus Kategori"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- KOLOM 2: MASTER MERK / BRAND -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4 flex flex-col">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <Tag class="w-4 h-4 text-blue-600" />
                                <h3 class="text-sm font-black text-slate-900">Master Merk / Brand</h3>
                            </div>
                            <span class="text-[11px] font-bold text-slate-400">{{ filteredMasterBrands.length }} Item</span>
                        </div>

                        <!-- Form Tambah Merk -->
                        <form @submit.prevent="submitNewBrand" class="flex gap-2">
                            <input 
                                v-model="newBrandNameInput"
                                type="text"
                                required
                                placeholder="Nama merk baru..."
                                class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-900 font-semibold focus:outline-none focus:border-amber-500 focus:bg-white transition"
                            />
                            <button 
                                type="submit"
                                :disabled="isCreatingBrand || !newBrandNameInput.trim()"
                                class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-1 shrink-0 transition disabled:opacity-50 cursor-pointer shadow-xs"
                            >
                                <Plus class="w-3.5 h-3.5 text-amber-400" />
                                <span>{{ isCreatingBrand ? '...' : 'Tambah' }}</span>
                            </button>
                        </form>

                        <!-- Search Merk -->
                        <div class="relative">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input 
                                v-model="masterBrandSearch"
                                type="text"
                                placeholder="Cari nama merk / brand..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                            />
                        </div>

                        <!-- Table List Merk -->
                        <div class="border border-slate-200 rounded-2xl overflow-hidden flex-1 max-h-[460px] overflow-y-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
                                    <tr class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                        <th class="py-2.5 px-3 w-10 text-center">No</th>
                                        <th class="py-2.5 px-3">Nama Merk</th>
                                        <th class="py-2.5 px-2 text-center w-20">Produk</th>
                                        <th class="py-2.5 px-2 text-right w-20">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-if="filteredMasterBrands.length === 0">
                                        <td colspan="4" class="py-6 text-center text-slate-400 text-xs">
                                            Tidak ada merk ditemukan.
                                        </td>
                                    </tr>
                                    <tr 
                                        v-for="(brand, idx) in filteredMasterBrands" 
                                        :key="brand.id"
                                        class="hover:bg-slate-50/80 transition"
                                    >
                                        <td class="py-2.5 px-3 text-center text-slate-400 font-mono text-[11px]">
                                            {{ idx + 1 }}
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <!-- Inline Edit Mode -->
                                            <div v-if="editingBrandId === brand.id" class="flex items-center gap-1" @click.stop>
                                                <input 
                                                    v-model="editBrandNameInput"
                                                    type="text"
                                                    class="flex-1 bg-white border border-amber-400 rounded px-2 py-0.5 text-xs text-slate-900 font-bold focus:outline-none ring-1 ring-amber-300"
                                                    @keyup.enter="saveEditBrand(brand)"
                                                    @keyup.esc="cancelEditBrand"
                                                    autofocus
                                                />
                                                <button 
                                                    @click="saveEditBrand(brand)"
                                                    type="button"
                                                    class="p-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded cursor-pointer"
                                                    title="Simpan"
                                                >
                                                    <Check class="w-3 h-3" />
                                                </button>
                                                <button 
                                                    @click="cancelEditBrand"
                                                    type="button"
                                                    class="p-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded cursor-pointer"
                                                    title="Batal"
                                                >
                                                    <X class="w-3 h-3" />
                                                </button>
                                            </div>
                                            <!-- Normal Display -->
                                            <div v-else class="font-bold text-slate-900 truncate max-w-[140px]" :title="brand.name">
                                                {{ brand.name }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-2 text-center">
                                            <span class="px-1.5 py-0.5 bg-slate-100 text-slate-700 rounded text-[10px] font-bold">
                                                {{ brand.products_count || 0 }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-2 text-right">
                                            <div v-if="editingBrandId !== brand.id" class="flex items-center justify-end gap-0.5">
                                                <button 
                                                    @click="startEditBrand(brand, $event)"
                                                    type="button"
                                                    class="p-1 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded transition cursor-pointer"
                                                    title="Edit Nama Merk"
                                                >
                                                    <Pencil class="w-3.5 h-3.5" />
                                                </button>
                                                <button 
                                                    @click="deleteBrand(brand, $event)"
                                                    type="button"
                                                    class="p-1 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded transition cursor-pointer"
                                                    title="Hapus Merk"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- KOLOM 3: MASTER SATUAN (UNITS) -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4 flex flex-col">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <Ruler class="w-4 h-4 text-purple-600" />
                                <h3 class="text-sm font-black text-slate-900">Master Satuan (Unit)</h3>
                            </div>
                            <span class="text-[11px] font-bold text-slate-400">{{ filteredMasterUnits.length }} Satuan</span>
                        </div>

                        <!-- Form Tambah Satuan -->
                        <form @submit.prevent="submitNewUnit" class="flex gap-2">
                            <input 
                                v-model="newUnitNameInput"
                                type="text"
                                required
                                placeholder="Nama satuan baru (misal: Roll)..."
                                class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-900 font-semibold focus:outline-none focus:border-amber-500 focus:bg-white transition"
                            />
                            <button 
                                type="submit"
                                :disabled="isCreatingUnit || !newUnitNameInput.trim()"
                                class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-1 shrink-0 transition disabled:opacity-50 cursor-pointer shadow-xs"
                            >
                                <Plus class="w-3.5 h-3.5 text-amber-400" />
                                <span>{{ isCreatingUnit ? '...' : 'Tambah' }}</span>
                            </button>
                        </form>

                        <!-- Search Satuan -->
                        <div class="relative">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input 
                                v-model="masterUnitSearch"
                                type="text"
                                placeholder="Cari nama satuan..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                            />
                        </div>

                        <!-- Table List Satuan -->
                        <div class="border border-slate-200 rounded-2xl overflow-hidden flex-1 max-h-[460px] overflow-y-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
                                    <tr class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                        <th class="py-2.5 px-3 w-10 text-center">No</th>
                                        <th class="py-2.5 px-3">Nama Satuan</th>
                                        <th class="py-2.5 px-2 text-center w-20">Dipakai</th>
                                        <th class="py-2.5 px-2 text-right w-20">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-if="filteredMasterUnits.length === 0">
                                        <td colspan="4" class="py-6 text-center text-slate-400 text-xs">
                                            Tidak ada satuan ditemukan.
                                        </td>
                                    </tr>
                                    <tr 
                                        v-for="(unit, idx) in filteredMasterUnits" 
                                        :key="unit.id"
                                        class="hover:bg-slate-50/80 transition"
                                    >
                                        <td class="py-2.5 px-3 text-center text-slate-400 font-mono text-[11px]">
                                            {{ idx + 1 }}
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <!-- Inline Edit Mode -->
                                            <div v-if="editingUnitId === unit.id" class="flex items-center gap-1" @click.stop>
                                                <input 
                                                    v-model="editUnitNameInput"
                                                    type="text"
                                                    class="flex-1 bg-white border border-amber-400 rounded px-2 py-0.5 text-xs text-slate-900 font-bold focus:outline-none ring-1 ring-amber-300"
                                                    @keyup.enter="saveEditUnit(unit)"
                                                    @keyup.esc="cancelEditUnit"
                                                    autofocus
                                                />
                                                <button 
                                                    @click="saveEditUnit(unit)"
                                                    type="button"
                                                    class="p-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded cursor-pointer"
                                                    title="Simpan"
                                                >
                                                    <Check class="w-3 h-3" />
                                                </button>
                                                <button 
                                                    @click="cancelEditUnit"
                                                    type="button"
                                                    class="p-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded cursor-pointer"
                                                    title="Batal"
                                                >
                                                    <X class="w-3 h-3" />
                                                </button>
                                            </div>
                                            <!-- Normal Display -->
                                            <div v-else class="font-bold text-slate-900 truncate max-w-[140px]" :title="unit.name">
                                                {{ unit.name }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-2 text-center">
                                            <span class="px-1.5 py-0.5 bg-slate-100 text-slate-700 rounded text-[10px] font-bold">
                                                {{ unit.product_units_count || 0 }}x
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-2 text-right">
                                            <div v-if="editingUnitId !== unit.id" class="flex items-center justify-end gap-0.5">
                                                <button 
                                                    @click="startEditUnit(unit, $event)"
                                                    type="button"
                                                    class="p-1 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded transition cursor-pointer"
                                                    title="Edit Nama Satuan"
                                                >
                                                    <Pencil class="w-3.5 h-3.5" />
                                                </button>
                                                <button 
                                                    @click="deleteUnit(unit, $event)"
                                                    type="button"
                                                    class="p-1 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded transition cursor-pointer"
                                                    title="Hapus Satuan"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Cetak Stiker Label Barcode & Price Tag -->
        <div v-if="isBarcodeModalOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between no-print bg-white">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                            <Barcode class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Cetak Label Barcode & Harga Barang</h3>
                            <p class="text-xs text-slate-500 font-medium">{{ barcodeProduct?.name }}</p>
                        </div>
                    </div>
                    <button @click="isBarcodeModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-5 bg-slate-50 border-b border-slate-200/80 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs no-print">
                    <div>
                        <label class="block text-slate-600 font-bold mb-1">Satuan Harga Label</label>
                        <select 
                            :value="barcodeSelectedUnit?.id" 
                            @change="barcodeSelectedUnit = barcodeProduct?.units.find(u => u.id === Number($event.target.value))" 
                            class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold"
                        >
                            <option v-for="u in barcodeProduct?.units" :key="u.id" :value="u.id">
                                {{ u.unit_name }} ({{ formatRupiah(u.price_retail) }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold mb-1">Jumlah Stiker</label>
                        <div class="flex items-center gap-1">
                            <input 
                                v-model.number="barcodePrintCount" 
                                type="number" 
                                min="1" 
                                max="100" 
                                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-black text-center"
                            />
                            <button type="button" @click="setPrintCount(1)" class="px-2 py-1.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold rounded-lg text-[10px] cursor-pointer">1</button>
                            <button type="button" @click="setPrintCount(6)" class="px-2 py-1.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold rounded-lg text-[10px] cursor-pointer">6</button>
                            <button type="button" @click="setPrintCount(12)" class="px-2 py-1.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold rounded-lg text-[10px] cursor-pointer">12</button>
                            <button type="button" @click="setPrintCount(24)" class="px-2 py-1.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold rounded-lg text-[10px] cursor-pointer">24</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold mb-1">Ukuran / Jenis Kertas</label>
                        <select v-model="barcodePaperType" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold">
                            <option value="thermal_33x19">🏷️ Stiker Label Mini 33 x 19 mm (Produk Kecil / Tempel Barang)</option>
                            <option value="thermal_50x30">📦 Stiker Thermal 50 x 30 mm (Standar Box / Dus)</option>
                            <option value="thermal_40x30">📄 Stiker Thermal 40 x 30 mm (Standar Sedang)</option>
                            <option value="grid_a4">📑 Kertas Lembar A4 (Grid Kotak)</option>
                        </select>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-6 bg-slate-100">
                    <div 
                        id="printable-barcode-sheet" 
                        :class="[
                            barcodePaperType === 'grid_a4' 
                                ? 'grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3' 
                                : 'flex flex-col items-center gap-3 thermal-roll-mode'
                        ]"
                    >
                        <div 
                            v-for="n in Math.min(Number(barcodePrintCount) || 1, 100)" 
                            :key="n"
                            :class="[
                                barcodePaperType === 'thermal_33x19'
                                    ? 'w-[33mm] h-[19mm] min-h-[19mm] max-h-[19mm] p-1 justify-between'
                                    : (barcodePaperType === 'thermal_40x30' ? 'w-[45mm] min-h-[30mm] p-2.5 justify-between' : 'w-[52mm] min-h-[33mm] p-2.5 justify-between'),
                                'sticker-card bg-white border border-slate-300 rounded-lg flex flex-col items-center text-center shadow-xs select-none shrink-0 overflow-hidden'
                            ]"
                        >
                            <!-- Mini 33x19 mm Compact Product Sticker Layout -->
                            <template v-if="barcodePaperType === 'thermal_33x19'">
                                <div class="w-full flex items-center justify-between leading-none mb-0.5 px-0.5">
                                    <span class="text-[6.5px] font-black tracking-tight text-slate-900 uppercase truncate max-w-[65%] text-left">
                                        {{ barcodeProduct?.name }}
                                    </span>
                                    <span class="text-[6px] text-amber-700 font-black uppercase truncate max-w-[35%] text-right">
                                        {{ barcodeProduct?.brand?.name || 'LISTRIK' }}
                                    </span>
                                </div>

                                <div class="w-full flex justify-center items-center overflow-hidden my-auto">
                                    <img v-if="barcodeDataUrl" :src="barcodeDataUrl" class="max-w-full max-h-[22px] object-contain mx-auto" />
                                </div>

                                <div class="w-full flex items-center justify-between leading-none mt-0.5 pt-0.5 border-t border-slate-200 px-0.5">
                                    <span class="text-[6px] font-mono text-slate-700 font-bold truncate max-w-[50%]">
                                        {{ barcodeProduct?.barcode || barcodeProduct?.sku }}
                                    </span>
                                    <span class="text-[7.5px] font-black text-slate-950 truncate max-w-[50%] text-right">
                                        {{ formatRupiah(barcodeSelectedUnit?.price_retail) }}
                                    </span>
                                </div>
                            </template>

                            <!-- Standard 50x30 / 40x30 / A4 Sticker Layout -->
                            <template v-else>
                                <div class="w-full flex items-center justify-between border-b border-slate-200 pb-1 mb-1">
                                    <span class="text-[8px] font-black tracking-tight text-slate-900 uppercase truncate">TRISNA JAYA</span>
                                    <span class="text-[7px] text-amber-700 font-bold uppercase truncate">{{ barcodeProduct?.brand?.name || 'LISTRIK' }}</span>
                                </div>

                                <div class="text-[9px] font-black text-slate-900 leading-tight line-clamp-2 min-h-[22px] w-full text-center">
                                    {{ barcodeProduct?.name }}
                                </div>

                                <div class="my-0.5 w-full flex justify-center items-center overflow-hidden">
                                    <img v-if="barcodeDataUrl" :src="barcodeDataUrl" class="max-w-full max-h-[50px] object-contain mx-auto" />
                                </div>

                                <div class="w-full pt-1 border-t border-slate-200 flex items-center justify-between">
                                    <span class="text-[8px] text-slate-500 font-semibold truncate">1 {{ barcodeSelectedUnit?.unit_name }}:</span>
                                    <span class="text-[11px] font-black text-slate-950 truncate">
                                        {{ formatRupiah(barcodeSelectedUnit?.price_retail) }}
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-white border-t border-slate-200 flex justify-between items-center no-print">
                    <div class="text-xs text-slate-500">
                        Total <strong>{{ barcodePrintCount }}</strong> stiker siap dicetak pada printer label barcode.
                    </div>
                    <div class="flex gap-2">
                        <button 
                            @click="isBarcodeModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs cursor-pointer"
                        >
                            Tutup
                        </button>
                        <button 
                            @click="printBarcodeStickers" 
                            class="px-6 py-2 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xs flex items-center gap-2 cursor-pointer shadow-md"
                        >
                            <Printer class="w-4 h-4 text-amber-400" />
                            <span>Cetak Barcode</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Tambah Produk Baru -->
        <div v-if="isAddModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Tambah Barang Listrik Baru</h3>
                        <p class="text-xs text-slate-500">Lengkapi data barang, satuan bertingkat, dan 4 klasifikasi harga.</p>
                    </div>
                    <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAddProduct" class="p-6 space-y-5 overflow-y-auto flex-1 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
                        <div>
                            <div class="h-5 flex items-center justify-between mb-1.5">
                                <label class="block text-slate-700 font-bold">Kode SKU</label>
                                <button 
                                    type="button" 
                                    @click="autoGenerateSku" 
                                    class="text-[10px] font-bold text-amber-700 hover:text-amber-900 flex items-center gap-1 cursor-pointer shrink-0"
                                    title="Generate ulang kode SKU otomatis"
                                >
                                    <Sparkles class="w-3 h-3 text-amber-600" />
                                    <span>⚡ Auto SKU</span>
                                </button>
                            </div>
                            <input 
                                v-model="addProductForm.sku" 
                                placeholder="Auto (misal: ELK-0001)" 
                                class="w-full h-9 bg-slate-50 border border-slate-200 rounded-xl px-3 text-slate-900 font-mono font-bold placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:bg-white transition text-xs" 
                            />
                        </div>
                        <div>
                            <div class="h-5 flex items-center justify-between mb-1.5">
                                <label class="block text-slate-700 font-bold">Barcode</label>
                                <button 
                                    type="button" 
                                    @click="autoGenerateBarcode" 
                                    class="text-[10px] font-bold text-sky-700 hover:text-sky-900 flex items-center gap-1 cursor-pointer shrink-0"
                                    title="Generate nomor barcode 8-digit internal"
                                >
                                    <Barcode class="w-3 h-3 text-sky-600" />
                                    <span>⚡ Auto 8-Digit</span>
                                </button>
                            </div>
                            <input 
                                v-model="addProductForm.barcode" 
                                placeholder="Scan kemasan / auto" 
                                class="w-full h-9 bg-slate-50 border border-slate-200 rounded-xl px-3 text-slate-900 font-mono font-bold placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:bg-white transition text-xs" 
                            />
                        </div>
                        <div>
                            <div class="h-5 flex items-center justify-between mb-1.5">
                                <label class="block text-slate-700 font-bold">Stok Fisik Awal</label>
                            </div>
                            <input 
                                v-model.number="addProductForm.stock_physical" 
                                type="number" 
                                placeholder="0" 
                                class="w-full h-9 bg-slate-50 border border-slate-200 rounded-xl px-3 text-slate-900 font-bold placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:bg-white transition text-xs" 
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold mb-1">Nama Barang Lengkap</label>
                        <input v-model="addProductForm.name" required placeholder="Contoh: Kabel NYM 2 x 1.5 mm Supreme (Putih SPLN)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-sm" />
                    </div>

                    <!-- Searchable Combobox for Kategori & Merk -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="relative">
                            <label class="block text-slate-600 font-bold mb-1">Kategori</label>
                            <button 
                                type="button"
                                @click="isCategoryDropdownOpen = !isCategoryDropdownOpen; isBrandDropdownOpen = false;"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-left text-xs font-semibold text-slate-800 flex items-center justify-between hover:border-amber-400 transition"
                            >
                                <span class="truncate">{{ selectedCategoryName }}</span>
                                <ChevronDown class="w-4 h-4 text-slate-400 shrink-0" />
                            </button>

                            <div 
                                v-if="isCategoryDropdownOpen" 
                                class="absolute top-full left-0 right-0 mt-1 z-40 bg-white border border-slate-200 rounded-2xl shadow-xl p-2 space-y-2 max-h-64 overflow-y-auto"
                            >
                                <div class="relative">
                                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                    <input 
                                        v-model="categorySearchQuery"
                                        type="text" 
                                        placeholder="Cari atau ketik nama kategori..."
                                        class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                                    />
                                </div>

                                <div class="space-y-0.5">
                                    <div 
                                        v-for="cat in filteredCategories" 
                                        :key="cat.id"
                                        class="group/item rounded-lg text-xs transition px-2.5 py-1.5 flex items-center justify-between"
                                        :class="[
                                            addProductForm.category_id === cat.id ? 'bg-amber-50 text-amber-900 font-bold' : 'text-slate-700 hover:bg-slate-50'
                                        ]"
                                    >
                                        <!-- Inline Edit Mode -->
                                        <div v-if="editingCategoryId === cat.id" class="flex items-center gap-1 w-full" @click.stop>
                                            <input 
                                                v-model="editCategoryNameInput"
                                                type="text"
                                                class="flex-1 bg-white border border-amber-400 rounded px-2 py-0.5 text-xs text-slate-900 font-bold focus:outline-none"
                                                @keyup.enter="saveEditCategory(cat)"
                                                @keyup.esc="cancelEditCategory"
                                                autofocus
                                            />
                                            <button @click="saveEditCategory(cat)" type="button" class="p-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded cursor-pointer" title="Simpan">
                                                <Check class="w-3 h-3" />
                                            </button>
                                            <button @click="cancelEditCategory" type="button" class="p-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded cursor-pointer" title="Batal">
                                                <X class="w-3 h-3" />
                                            </button>
                                        </div>

                                        <!-- Normal Selection Row -->
                                        <template v-else>
                                            <div @click="selectCategory(cat.id)" class="flex-1 truncate cursor-pointer flex items-center gap-2">
                                                <span>{{ cat.name }}</span>
                                                <span v-if="cat.products_count !== undefined" class="text-[10px] text-slate-400 font-normal">({{ cat.products_count }})</span>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0 ml-2">
                                                <Check v-if="addProductForm.category_id === cat.id" class="w-3.5 h-3.5 text-amber-600 mr-1" />
                                                <button 
                                                    @click.stop="startEditCategory(cat, $event)" 
                                                    type="button" 
                                                    class="p-1 text-slate-400 hover:text-amber-600 hover:bg-white rounded transition cursor-pointer" 
                                                    title="Edit Nama Kategori"
                                                >
                                                    <Pencil class="w-3 h-3" />
                                                </button>
                                                <button 
                                                    @click.stop="deleteCategory(cat, $event)" 
                                                    type="button" 
                                                    class="p-1 text-slate-400 hover:text-rose-600 hover:bg-white rounded transition cursor-pointer" 
                                                    title="Hapus Kategori"
                                                >
                                                    <Trash2 class="w-3 h-3" />
                                                </button>
                                            </div>
                                        </template>
                                    </div>

                                    <div 
                                        v-if="!isCategoryExactMatch && categorySearchQuery.trim()" 
                                        @click="createAndSelectCategory"
                                        class="p-2 bg-amber-50 hover:bg-amber-100 text-amber-900 rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1.5 border border-amber-200 mt-1"
                                    >
                                        <PlusCircle class="w-4 h-4 text-amber-700" />
                                        <span>+ Tambah Kategori: "<strong>{{ categorySearchQuery }}</strong>"</span>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <span class="text-slate-400">{{ categories.length }} kategori</span>
                                    <button 
                                        type="button" 
                                        @click="isMasterCategoryBrandModalOpen = true; isCategoryDropdownOpen = false; masterTab = 'categories';" 
                                        class="text-amber-700 font-bold hover:underline cursor-pointer flex items-center gap-1"
                                    >
                                        <SlidersHorizontal class="w-3 h-3" />
                                        <span>Kelola Master</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="relative">
                            <label class="block text-slate-600 font-bold mb-1">Merk / Brand</label>
                            <button 
                                type="button"
                                @click="isBrandDropdownOpen = !isBrandDropdownOpen; isCategoryDropdownOpen = false;"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-left text-xs font-semibold text-slate-800 flex items-center justify-between hover:border-amber-400 transition"
                            >
                                <span class="truncate">{{ selectedBrandName }}</span>
                                <ChevronDown class="w-4 h-4 text-slate-400 shrink-0" />
                            </button>

                            <div 
                                v-if="isBrandDropdownOpen" 
                                class="absolute top-full left-0 right-0 mt-1 z-40 bg-white border border-slate-200 rounded-2xl shadow-xl p-2 space-y-2 max-h-64 overflow-y-auto"
                            >
                                <div class="relative">
                                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                    <input 
                                        v-model="brandSearchQuery"
                                        type="text" 
                                        placeholder="Cari atau ketik nama merk..."
                                        class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                                    />
                                </div>

                                <div class="space-y-0.5">
                                    <div 
                                        v-for="brand in filteredBrands" 
                                        :key="brand.id"
                                        class="group/item rounded-lg text-xs transition px-2.5 py-1.5 flex items-center justify-between"
                                        :class="[
                                            addProductForm.brand_id === brand.id ? 'bg-amber-50 text-amber-900 font-bold' : 'text-slate-700 hover:bg-slate-50'
                                        ]"
                                    >
                                        <!-- Inline Edit Mode -->
                                        <div v-if="editingBrandId === brand.id" class="flex items-center gap-1 w-full" @click.stop>
                                            <input 
                                                v-model="editBrandNameInput"
                                                type="text"
                                                class="flex-1 bg-white border border-amber-400 rounded px-2 py-0.5 text-xs text-slate-900 font-bold focus:outline-none"
                                                @keyup.enter="saveEditBrand(brand)"
                                                @keyup.esc="cancelEditBrand"
                                                autofocus
                                            />
                                            <button @click="saveEditBrand(brand)" type="button" class="p-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded cursor-pointer" title="Simpan">
                                                <Check class="w-3 h-3" />
                                            </button>
                                            <button @click="cancelEditBrand" type="button" class="p-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded cursor-pointer" title="Batal">
                                                <X class="w-3 h-3" />
                                            </button>
                                        </div>

                                        <!-- Normal Selection Row -->
                                        <template v-else>
                                            <div @click="selectBrand(brand.id)" class="flex-1 truncate cursor-pointer flex items-center gap-2">
                                                <span>{{ brand.name }}</span>
                                                <span v-if="brand.products_count !== undefined" class="text-[10px] text-slate-400 font-normal">({{ brand.products_count }})</span>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0 ml-2">
                                                <Check v-if="addProductForm.brand_id === brand.id" class="w-3.5 h-3.5 text-amber-600 mr-1" />
                                                <button 
                                                    @click.stop="startEditBrand(brand, $event)" 
                                                    type="button" 
                                                    class="p-1 text-slate-400 hover:text-amber-600 hover:bg-white rounded transition cursor-pointer" 
                                                    title="Edit Nama Merk"
                                                >
                                                    <Pencil class="w-3 h-3" />
                                                </button>
                                                <button 
                                                    @click.stop="deleteBrand(brand, $event)" 
                                                    type="button" 
                                                    class="p-1 text-slate-400 hover:text-rose-600 hover:bg-white rounded transition cursor-pointer" 
                                                    title="Hapus Merk"
                                                >
                                                    <Trash2 class="w-3 h-3" />
                                                </button>
                                            </div>
                                        </template>
                                    </div>

                                    <div 
                                        v-if="!isBrandExactMatch && brandSearchQuery.trim()" 
                                        @click="createAndSelectBrand"
                                        class="p-2 bg-amber-50 hover:bg-amber-100 text-amber-900 rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1.5 border border-amber-200 mt-1"
                                    >
                                        <PlusCircle class="w-4 h-4 text-amber-700" />
                                        <span>+ Tambah Merk: "<strong>{{ brandSearchQuery }}</strong>"</span>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <span class="text-slate-400">{{ brands.length }} merk</span>
                                    <button 
                                        type="button" 
                                        @click="isMasterCategoryBrandModalOpen = true; isBrandDropdownOpen = false; masterTab = 'brands';" 
                                        class="text-amber-700 font-bold hover:underline cursor-pointer flex items-center gap-1"
                                    >
                                        <SlidersHorizontal class="w-3 h-3" />
                                        <span>Kelola Master</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Multi-Units and 4 Price Tiers Table -->
                    <div class="space-y-3 pt-3 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-black uppercase text-slate-900">Satuan & 4 Tingkatan Harga</h4>
                                <p class="text-[11px] text-slate-500">Tentukan harga untuk Eceran, Tukang, Kontraktor, dan Grosir per satuan.</p>
                            </div>
                            <button 
                                type="button" 
                                @click="addUnitRow" 
                                class="px-3 py-1.5 rounded-xl bg-amber-100 text-amber-900 hover:bg-amber-200 font-bold text-[11px] flex items-center gap-1 transition cursor-pointer"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Tambah Satuan Lain (Misal: Roll / Dus)</span>
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div 
                                v-for="(unit, idx) in addProductForm.units" 
                                :key="idx"
                                class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl space-y-2.5"
                            >
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/80 pb-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-bold text-slate-800 text-xs shrink-0">Satuan {{ idx + 1 }}:</span>
                                        <div class="relative">
                                            <input 
                                                v-model="unit.unit_name" 
                                                list="master-units-datalist"
                                                placeholder="Pilih / ketik satuan..." 
                                                class="bg-white border border-slate-300 rounded-lg px-2.5 py-1 text-xs text-slate-900 font-bold w-36 focus:outline-none focus:border-amber-500 shadow-xs" 
                                            />
                                        </div>
                                        <span v-if="unit.is_base_unit" class="text-[9px] font-black uppercase px-2 py-0.5 bg-slate-200 text-slate-700 rounded font-mono shrink-0">Satuan Dasar</span>

                                        <!-- Quick Selection Pills -->
                                        <div class="flex flex-wrap items-center gap-1 ml-1">
                                            <button 
                                                v-for="u in (units || []).slice(0, 5)" 
                                                :key="u.id" 
                                                type="button" 
                                                @click="unit.unit_name = u.name" 
                                                :class="unit.unit_name === u.name ? 'bg-amber-500 text-white font-black border-amber-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100 font-medium'" 
                                                class="px-2 py-0.5 rounded-md border text-[10px] transition cursor-pointer"
                                            >
                                                {{ u.name }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-1 text-[11px]">
                                            <span class="text-slate-500">1 {{ unit.unit_name }} =</span>
                                            <input v-model.number="unit.conversion_ratio" type="number" step="0.1" class="w-16 bg-white border border-slate-200 rounded-lg px-2 py-0.5 text-center font-bold" />
                                            <span class="text-slate-500">satuan dasar</span>
                                        </div>

                                        <button v-if="!unit.is_base_unit" type="button" @click="removeUnitRow(idx)" class="text-slate-400 hover:text-rose-600 p-1">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <div :class="['grid gap-2', canSeeCostPrice ? 'grid-cols-2' : 'grid-cols-1']">
                                    <div v-if="canSeeCostPrice">
                                        <label class="block text-[10px] font-bold text-slate-400 mb-0.5">Modal (HPP)</label>
                                        <input v-model.number="unit.cost_price" type="number" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1.5 text-xs text-slate-800 font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-700 mb-0.5">Harga Jual</label>
                                        <input v-model.number="unit.price_retail" type="number" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1.5 text-xs text-slate-900 font-black" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex justify-end gap-2">
                        <button type="button" @click="isAddModalOpen = false" class="px-4 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" :disabled="addProductForm.processing" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xs cursor-pointer shadow-xs">
                            Simpan Produk Baru
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Edit Data Produk & Harga -->
        <div v-if="isEditModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Edit Data Produk Listrik</h3>
                        <p class="text-xs text-slate-500">Perbarui informasi barang, SKU, barcode, serta harga multi-satuan bertingkat.</p>
                    </div>
                    <button @click="isEditModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEditProduct" class="p-6 space-y-5 overflow-y-auto flex-1 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
                        <div>
                            <div class="h-5 flex items-center justify-between mb-1.5">
                                <label class="block text-slate-700 font-bold">Kode SKU</label>
                            </div>
                            <input 
                                v-model="editProductForm.sku" 
                                required 
                                class="w-full h-9 bg-slate-50 border border-slate-200 rounded-xl px-3 text-slate-900 font-mono font-bold focus:outline-none focus:border-amber-500 focus:bg-white transition text-xs" 
                            />
                        </div>
                        <div>
                            <div class="h-5 flex items-center justify-between mb-1.5">
                                <label class="block text-slate-700 font-bold">Barcode</label>
                                <button 
                                    type="button" 
                                    @click="autoGenerateEditBarcode" 
                                    class="text-[10px] font-bold text-sky-700 hover:text-sky-900 flex items-center gap-1 cursor-pointer shrink-0"
                                    title="Generate nomor barcode 8-digit internal"
                                >
                                    <Barcode class="w-3 h-3 text-sky-600" />
                                    <span>⚡ Auto 8-Digit</span>
                                </button>
                            </div>
                            <input 
                                v-model="editProductForm.barcode" 
                                placeholder="Scan kemasan / auto" 
                                class="w-full h-9 bg-slate-50 border border-slate-200 rounded-xl px-3 text-slate-900 font-mono font-bold placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:bg-white transition text-xs" 
                            />
                        </div>
                        <div>
                            <div class="h-5 flex items-center justify-between mb-1.5">
                                <label class="block text-slate-700 font-bold">Peringatan Min. Stok</label>
                            </div>
                            <input 
                                v-model.number="editProductForm.min_stock" 
                                type="number" 
                                required 
                                class="w-full h-9 bg-slate-50 border border-slate-200 rounded-xl px-3 text-slate-900 font-bold focus:outline-none focus:border-amber-500 focus:bg-white transition text-xs" 
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold mb-1">Nama Barang Lengkap</label>
                        <input v-model="editProductForm.name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-sm" />
                    </div>

                    <!-- Searchable Combobox for Kategori & Merk -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="relative">
                            <label class="block text-slate-600 font-bold mb-1">Kategori</label>
                            <button 
                                type="button"
                                @click="isEditCategoryDropdownOpen = !isEditCategoryDropdownOpen; isEditBrandDropdownOpen = false;"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-left text-xs font-semibold text-slate-800 flex items-center justify-between hover:border-amber-400 transition"
                            >
                                <span class="truncate">{{ selectedEditCategoryName }}</span>
                                <ChevronDown class="w-4 h-4 text-slate-400 shrink-0" />
                            </button>

                            <div 
                                v-if="isEditCategoryDropdownOpen" 
                                class="absolute top-full left-0 right-0 mt-1 z-40 bg-white border border-slate-200 rounded-2xl shadow-xl p-2 space-y-2 max-h-64 overflow-y-auto"
                            >
                                <div class="relative">
                                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                    <input 
                                        v-model="editCategorySearchQuery"
                                        type="text" 
                                        placeholder="Cari atau ketik nama kategori..."
                                        class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                                    />
                                </div>

                                <div class="space-y-0.5">
                                    <div 
                                        v-for="cat in filteredEditCategories" 
                                        :key="cat.id"
                                        class="group/item rounded-lg text-xs transition px-2.5 py-1.5 flex items-center justify-between"
                                        :class="[
                                            editProductForm.category_id === cat.id ? 'bg-amber-50 text-amber-900 font-bold' : 'text-slate-700 hover:bg-slate-50'
                                        ]"
                                    >
                                        <!-- Inline Edit Mode -->
                                        <div v-if="editingCategoryId === cat.id" class="flex items-center gap-1 w-full" @click.stop>
                                            <input 
                                                v-model="editCategoryNameInput"
                                                type="text"
                                                class="flex-1 bg-white border border-amber-400 rounded px-2 py-0.5 text-xs text-slate-900 font-bold focus:outline-none"
                                                @keyup.enter="saveEditCategory(cat)"
                                                @keyup.esc="cancelEditCategory"
                                                autofocus
                                            />
                                            <button @click="saveEditCategory(cat)" type="button" class="p-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded cursor-pointer" title="Simpan">
                                                <Check class="w-3 h-3" />
                                            </button>
                                            <button @click="cancelEditCategory" type="button" class="p-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded cursor-pointer" title="Batal">
                                                <X class="w-3 h-3" />
                                            </button>
                                        </div>

                                        <!-- Normal Selection Row -->
                                        <template v-else>
                                            <div @click="selectEditCategory(cat.id)" class="flex-1 truncate cursor-pointer flex items-center gap-2">
                                                <span>{{ cat.name }}</span>
                                                <span v-if="cat.products_count !== undefined" class="text-[10px] text-slate-400 font-normal">({{ cat.products_count }})</span>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0 ml-2">
                                                <Check v-if="editProductForm.category_id === cat.id" class="w-3.5 h-3.5 text-amber-600 mr-1" />
                                                <button 
                                                    @click.stop="startEditCategory(cat, $event)" 
                                                    type="button" 
                                                    class="p-1 text-slate-400 hover:text-amber-600 hover:bg-white rounded transition cursor-pointer" 
                                                    title="Edit Nama Kategori"
                                                >
                                                    <Pencil class="w-3 h-3" />
                                                </button>
                                                <button 
                                                    @click.stop="deleteCategory(cat, $event)" 
                                                    type="button" 
                                                    class="p-1 text-slate-400 hover:text-rose-600 hover:bg-white rounded transition cursor-pointer" 
                                                    title="Hapus Kategori"
                                                >
                                                    <Trash2 class="w-3 h-3" />
                                                </button>
                                            </div>
                                        </template>
                                    </div>

                                    <div 
                                        v-if="!isEditCategoryExactMatch && editCategorySearchQuery.trim()" 
                                        @click="createAndSelectEditCategory"
                                        class="p-2 bg-amber-50 hover:bg-amber-100 text-amber-900 rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1.5 border border-amber-200 mt-1"
                                    >
                                        <PlusCircle class="w-4 h-4 text-amber-700" />
                                        <span>+ Tambah Kategori: "<strong>{{ editCategorySearchQuery }}</strong>"</span>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <span class="text-slate-400">{{ categories.length }} kategori</span>
                                    <button 
                                        type="button" 
                                        @click="isMasterCategoryBrandModalOpen = true; isEditCategoryDropdownOpen = false; masterTab = 'categories';" 
                                        class="text-amber-700 font-bold hover:underline cursor-pointer flex items-center gap-1"
                                    >
                                        <SlidersHorizontal class="w-3 h-3" />
                                        <span>Kelola Master</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="relative">
                            <label class="block text-slate-600 font-bold mb-1">Merk / Brand</label>
                            <button 
                                type="button"
                                @click="isEditBrandDropdownOpen = !isEditBrandDropdownOpen; isEditCategoryDropdownOpen = false;"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-left text-xs font-semibold text-slate-800 flex items-center justify-between hover:border-amber-400 transition"
                            >
                                <span class="truncate">{{ selectedEditBrandName }}</span>
                                <ChevronDown class="w-4 h-4 text-slate-400 shrink-0" />
                            </button>

                            <div 
                                v-if="isEditBrandDropdownOpen" 
                                class="absolute top-full left-0 right-0 mt-1 z-40 bg-white border border-slate-200 rounded-2xl shadow-xl p-2 space-y-2 max-h-64 overflow-y-auto"
                            >
                                <div class="relative">
                                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                    <input 
                                        v-model="editBrandSearchQuery"
                                        type="text" 
                                        placeholder="Cari atau ketik nama merk..."
                                        class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                                    />
                                </div>

                                <div class="space-y-0.5">
                                    <div 
                                        v-for="brand in filteredEditBrands" 
                                        :key="brand.id"
                                        class="group/item rounded-lg text-xs transition px-2.5 py-1.5 flex items-center justify-between"
                                        :class="[
                                            editProductForm.brand_id === brand.id ? 'bg-amber-50 text-amber-900 font-bold' : 'text-slate-700 hover:bg-slate-50'
                                        ]"
                                    >
                                        <!-- Inline Edit Mode -->
                                        <div v-if="editingBrandId === brand.id" class="flex items-center gap-1 w-full" @click.stop>
                                            <input 
                                                v-model="editBrandNameInput"
                                                type="text"
                                                class="flex-1 bg-white border border-amber-400 rounded px-2 py-0.5 text-xs text-slate-900 font-bold focus:outline-none"
                                                @keyup.enter="saveEditBrand(brand)"
                                                @keyup.esc="cancelEditBrand"
                                                autofocus
                                            />
                                            <button @click="saveEditBrand(brand)" type="button" class="p-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded cursor-pointer" title="Simpan">
                                                <Check class="w-3 h-3" />
                                            </button>
                                            <button @click="cancelEditBrand" type="button" class="p-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded cursor-pointer" title="Batal">
                                                <X class="w-3 h-3" />
                                            </button>
                                        </div>

                                        <!-- Normal Selection Row -->
                                        <template v-else>
                                            <div @click="selectEditBrand(brand.id)" class="flex-1 truncate cursor-pointer flex items-center gap-2">
                                                <span>{{ brand.name }}</span>
                                                <span v-if="brand.products_count !== undefined" class="text-[10px] text-slate-400 font-normal">({{ brand.products_count }})</span>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0 ml-2">
                                                <Check v-if="editProductForm.brand_id === brand.id" class="w-3.5 h-3.5 text-amber-600 mr-1" />
                                                <button 
                                                    @click.stop="startEditBrand(brand, $event)" 
                                                    type="button" 
                                                    class="p-1 text-slate-400 hover:text-amber-600 hover:bg-white rounded transition cursor-pointer" 
                                                    title="Edit Nama Merk"
                                                >
                                                    <Pencil class="w-3 h-3" />
                                                </button>
                                                <button 
                                                    @click.stop="deleteBrand(brand, $event)" 
                                                    type="button" 
                                                    class="p-1 text-slate-400 hover:text-rose-600 hover:bg-white rounded transition cursor-pointer" 
                                                    title="Hapus Merk"
                                                >
                                                    <Trash2 class="w-3 h-3" />
                                                </button>
                                            </div>
                                        </template>
                                    </div>

                                    <div 
                                        v-if="!isEditBrandExactMatch && editBrandSearchQuery.trim()" 
                                        @click="createAndSelectEditBrand"
                                        class="p-2 bg-amber-50 hover:bg-amber-100 text-amber-900 rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1.5 border border-amber-200 mt-1"
                                    >
                                        <PlusCircle class="w-4 h-4 text-amber-700" />
                                        <span>+ Tambah Merk: "<strong>{{ editBrandSearchQuery }}</strong>"</span>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <span class="text-slate-400">{{ brands.length }} merk</span>
                                    <button 
                                        type="button" 
                                        @click="isMasterCategoryBrandModalOpen = true; isEditBrandDropdownOpen = false; masterTab = 'brands';" 
                                        class="text-amber-700 font-bold hover:underline cursor-pointer flex items-center gap-1"
                                    >
                                        <SlidersHorizontal class="w-3 h-3" />
                                        <span>Kelola Master</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Multi-Units and 4 Price Tiers Table in Edit -->
                    <div class="space-y-3 pt-3 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-black uppercase text-slate-900">Satuan & 4 Tingkatan Harga</h4>
                                <p class="text-[11px] text-slate-500">Sesuaikan harga untuk Eceran, Tukang, Kontraktor, dan Grosir per satuan.</p>
                            </div>
                            <button 
                                type="button" 
                                @click="addEditUnitRow" 
                                class="px-3 py-1.5 rounded-xl bg-amber-100 text-amber-900 hover:bg-amber-200 font-bold text-[11px] flex items-center gap-1 transition cursor-pointer"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Tambah Satuan Lain</span>
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div 
                                v-for="(unit, idx) in editProductForm.units" 
                                :key="idx"
                                class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl space-y-2.5"
                            >
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/80 pb-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-bold text-slate-800 text-xs shrink-0">Satuan {{ idx + 1 }}:</span>
                                        <div class="relative">
                                            <input 
                                                v-model="unit.unit_name" 
                                                list="master-units-datalist"
                                                placeholder="Pilih / ketik satuan..." 
                                                class="bg-white border border-slate-300 rounded-lg px-2.5 py-1 text-xs text-slate-900 font-bold w-36 focus:outline-none focus:border-amber-500 shadow-xs" 
                                            />
                                        </div>
                                        <span v-if="unit.is_base_unit" class="text-[9px] font-black uppercase px-2 py-0.5 bg-slate-200 text-slate-700 rounded font-mono shrink-0">Satuan Dasar</span>

                                        <!-- Quick Selection Pills -->
                                        <div class="flex flex-wrap items-center gap-1 ml-1">
                                            <button 
                                                v-for="u in (units || []).slice(0, 5)" 
                                                :key="u.id" 
                                                type="button" 
                                                @click="unit.unit_name = u.name" 
                                                :class="unit.unit_name === u.name ? 'bg-amber-500 text-white font-black border-amber-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100 font-medium'" 
                                                class="px-2 py-0.5 rounded-md border text-[10px] transition cursor-pointer"
                                            >
                                                {{ u.name }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-1 text-[11px]">
                                            <span class="text-slate-500">1 {{ unit.unit_name }} =</span>
                                            <input v-model.number="unit.conversion_ratio" type="number" step="0.1" class="w-16 bg-white border border-slate-200 rounded-lg px-2 py-0.5 text-center font-bold" />
                                            <span class="text-slate-500">satuan dasar</span>
                                        </div>

                                        <button v-if="!unit.is_base_unit" type="button" @click="removeEditUnitRow(idx)" class="text-slate-400 hover:text-rose-600 p-1 cursor-pointer">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <div :class="['grid gap-2', canSeeCostPrice ? 'grid-cols-2' : 'grid-cols-1']">
                                    <div v-if="canSeeCostPrice">
                                        <label class="block text-[10px] font-bold text-slate-400 mb-0.5">Modal (HPP)</label>
                                        <input v-model.number="unit.cost_price" type="number" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1.5 text-xs text-slate-800 font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-700 mb-0.5">Harga Jual</label>
                                        <input v-model.number="unit.price_retail" type="number" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1.5 text-xs text-slate-900 font-black" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                        <button 
                            type="button" 
                            @click="deleteProduct(editingProduct)" 
                            class="px-3.5 py-2 rounded-xl text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 font-bold text-xs flex items-center gap-1.5 transition cursor-pointer"
                        >
                            <Trash2 class="w-4 h-4" />
                            <span>Hapus Produk</span>
                        </button>

                        <div class="flex gap-2">
                            <button type="button" @click="isEditModalOpen = false" class="px-4 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer">
                                Batal
                            </button>
                            <button type="submit" :disabled="editProductForm.processing" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xs cursor-pointer shadow-xs">
                                Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Stock Adjustment Quick Modal -->
        <div v-if="isAdjustModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Penyesuaian Cepat Stok Fisik</h3>
                        <p class="text-xs text-slate-500">{{ selectedProduct?.name }}</p>
                    </div>
                    <button @click="isAdjustModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-600 font-bold uppercase tracking-wider mb-1">Tipe Penyesuaian</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button 
                                v-for="t in [
                                    { id: 'in', label: 'Barang Masuk (+)' },
                                    { id: 'out', label: 'Barang Keluar (-)' },
                                    { id: 'adjustment', label: 'Set Stok Fisik' }
                                ]" 
                                :key="t.id"
                                type="button"
                                @click="adjustForm.type = t.id"
                                :class="adjustForm.type === t.id ? 'bg-slate-900 text-white font-bold' : 'bg-slate-50 text-slate-600 border border-slate-200'"
                                class="py-2 text-[10px] rounded-xl transition text-center cursor-pointer"
                            >
                                {{ t.label }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold uppercase tracking-wider mb-1">Jumlah (Base Unit)</label>
                        <input 
                            v-model.number="adjustForm.qty"
                            type="number"
                            step="0.1"
                            min="0.1"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-amber-500 font-bold"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-600 font-bold uppercase tracking-wider mb-1">Alasan Penyesuaian</label>
                        <input 
                            v-model="adjustForm.reason"
                            type="text"
                            placeholder="Contoh: Barang datang dari distributor / opname..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <button 
                        @click="submitAdjust"
                        :disabled="adjustForm.processing"
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-2.5 rounded-xl transition text-xs mt-2 cursor-pointer"
                    >
                        Simpan Perubahan Stok
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: Master Kategori & Merk (Brand) -->
        <div v-if="isMasterCategoryBrandModalOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-4xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                <!-- Modal Header -->
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-white">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                            <Tag class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Kelola Master Kategori & Merk</h3>
                            <p class="text-xs text-slate-500 font-medium">Edit nama, perbaiki typo/duplikat, atau hapus kategori & merk.</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <!-- Sub-tab switch inside modal -->
                        <div class="flex items-center bg-slate-100 p-1 rounded-xl text-xs font-bold">
                            <button 
                                type="button"
                                @click="masterTab = 'categories'"
                                :class="masterTab === 'categories' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
                                class="px-3 py-1 rounded-lg transition cursor-pointer"
                            >
                                Kategori ({{ categories.length }})
                            </button>
                            <button 
                                type="button"
                                @click="masterTab = 'brands'"
                                :class="masterTab === 'brands' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
                                class="px-3 py-1 rounded-lg transition cursor-pointer"
                            >
                                Merk ({{ brands.length }})
                            </button>
                            <button 
                                type="button"
                                @click="masterTab = 'units'"
                                :class="masterTab === 'units' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
                                class="px-3 py-1 rounded-lg transition cursor-pointer"
                            >
                                Satuan ({{ (units || []).length }})
                            </button>
                        </div>

                        <button @click="isMasterCategoryBrandModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto flex-1 space-y-4">
                    <!-- SUB-TAB 1: KATEGORI -->
                    <div v-if="masterTab === 'categories'" class="space-y-4">
                        <!-- Add Form & Search -->
                        <div class="flex flex-col sm:flex-row gap-3">
                            <form @submit.prevent="submitNewCategory" class="flex-1 flex gap-2">
                                <input 
                                    v-model="newCategoryNameInput"
                                    type="text"
                                    required
                                    placeholder="Nama kategori baru..."
                                    class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-900 font-semibold focus:outline-none focus:border-amber-500 focus:bg-white"
                                />
                                <button 
                                    type="submit"
                                    :disabled="isCreatingCategory || !newCategoryNameInput.trim()"
                                    class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 shrink-0 transition disabled:opacity-50 cursor-pointer shadow-xs"
                                >
                                    <Plus class="w-3.5 h-3.5 text-amber-400" />
                                    <span>{{ isCreatingCategory ? 'Menyimpan...' : 'Tambah' }}</span>
                                </button>
                            </form>

                            <div class="relative sm:w-64">
                                <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input 
                                    v-model="masterCategorySearch"
                                    type="text"
                                    placeholder="Cari kategori..."
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                                />
                            </div>
                        </div>

                        <!-- Table List Kategori -->
                        <div class="border border-slate-200 rounded-2xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                        <th class="py-2.5 px-3.5 w-12 text-center">No</th>
                                        <th class="py-2.5 px-3.5">Nama Kategori</th>
                                        <th class="py-2.5 px-3.5 text-center w-32">Jml Produk Terdaftar</th>
                                        <th class="py-2.5 px-3.5 text-right w-24">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-if="filteredMasterCategories.length === 0">
                                        <td colspan="4" class="py-8 text-center text-slate-400 text-xs">
                                            Tidak ada kategori ditemukan.
                                        </td>
                                    </tr>
                                    <tr 
                                        v-for="(cat, idx) in filteredMasterCategories" 
                                        :key="cat.id"
                                        class="hover:bg-slate-50/80 transition"
                                    >
                                        <td class="py-2.5 px-3.5 text-center text-slate-400 font-mono text-[11px]">
                                            {{ idx + 1 }}
                                        </td>
                                        <td class="py-2.5 px-3.5">
                                            <div v-if="editingCategoryId === cat.id" class="flex items-center gap-1.5" @click.stop>
                                                <input 
                                                    v-model="editCategoryNameInput"
                                                    type="text"
                                                    class="flex-1 bg-white border border-amber-400 rounded-lg px-2.5 py-1 text-xs text-slate-900 font-bold focus:outline-none ring-2 ring-amber-200"
                                                    @keyup.enter="saveEditCategory(cat)"
                                                    @keyup.esc="cancelEditCategory"
                                                    autofocus
                                                />
                                                <button @click="saveEditCategory(cat)" type="button" class="p-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg cursor-pointer" title="Simpan">
                                                    <Check class="w-3.5 h-3.5" />
                                                </button>
                                                <button @click="cancelEditCategory" type="button" class="p-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg cursor-pointer" title="Batal">
                                                    <X class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                            <div v-else class="font-bold text-slate-900">
                                                {{ cat.name }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3.5 text-center">
                                            <span class="px-2.5 py-0.5 bg-slate-100 text-slate-700 rounded-full text-[10px] font-bold">
                                                {{ cat.products_count || 0 }} produk
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3.5 text-right">
                                            <div v-if="editingCategoryId !== cat.id" class="flex items-center justify-end gap-1">
                                                <button 
                                                    @click="startEditCategory(cat, $event)"
                                                    type="button"
                                                    class="p-1.5 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition cursor-pointer"
                                                    title="Edit Nama Kategori"
                                                >
                                                    <Pencil class="w-3.5 h-3.5" />
                                                </button>
                                                <button 
                                                    @click="deleteCategory(cat, $event)"
                                                    type="button"
                                                    class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                                    title="Hapus Kategori"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SUB-TAB 2: MERK / BRAND -->
                    <div v-else-if="masterTab === 'brands'" class="space-y-4">
                        <!-- Add Form & Search -->
                        <div class="flex flex-col sm:flex-row gap-3">
                            <form @submit.prevent="submitNewBrand" class="flex-1 flex gap-2">
                                <input 
                                    v-model="newBrandNameInput"
                                    type="text"
                                    required
                                    placeholder="Nama merk / brand baru..."
                                    class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-900 font-semibold focus:outline-none focus:border-amber-500 focus:bg-white"
                                />
                                <button 
                                    type="submit"
                                    :disabled="isCreatingBrand || !newBrandNameInput.trim()"
                                    class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 shrink-0 transition disabled:opacity-50 cursor-pointer shadow-xs"
                                >
                                    <Plus class="w-3.5 h-3.5 text-amber-400" />
                                    <span>{{ isCreatingBrand ? 'Menyimpan...' : 'Tambah' }}</span>
                                </button>
                            </form>

                            <div class="relative sm:w-64">
                                <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input 
                                    v-model="masterBrandSearch"
                                    type="text"
                                    placeholder="Cari merk / brand..."
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                                />
                            </div>
                        </div>

                        <!-- Table List Merk -->
                        <div class="border border-slate-200 rounded-2xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                        <th class="py-2.5 px-3.5 w-12 text-center">No</th>
                                        <th class="py-2.5 px-3.5">Nama Merk / Brand</th>
                                        <th class="py-2.5 px-3.5 text-center w-32">Jml Produk Terdaftar</th>
                                        <th class="py-2.5 px-3.5 text-right w-24">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-if="filteredMasterBrands.length === 0">
                                        <td colspan="4" class="py-8 text-center text-slate-400 text-xs">
                                            Tidak ada merk / brand ditemukan.
                                        </td>
                                    </tr>
                                    <tr 
                                        v-for="(brand, idx) in filteredMasterBrands" 
                                        :key="brand.id"
                                        class="hover:bg-slate-50/80 transition"
                                    >
                                        <td class="py-2.5 px-3.5 text-center text-slate-400 font-mono text-[11px]">
                                            {{ idx + 1 }}
                                        </td>
                                        <td class="py-2.5 px-3.5">
                                            <div v-if="editingBrandId === brand.id" class="flex items-center gap-1.5" @click.stop>
                                                <input 
                                                    v-model="editBrandNameInput"
                                                    type="text"
                                                    class="flex-1 bg-white border border-amber-400 rounded-lg px-2.5 py-1 text-xs text-slate-900 font-bold focus:outline-none ring-2 ring-amber-200"
                                                    @keyup.enter="saveEditBrand(brand)"
                                                    @keyup.esc="cancelEditBrand"
                                                    autofocus
                                                />
                                                <button @click="saveEditBrand(brand)" type="button" class="p-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg cursor-pointer" title="Simpan">
                                                    <Check class="w-3.5 h-3.5" />
                                                </button>
                                                <button @click="cancelEditBrand" type="button" class="p-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg cursor-pointer" title="Batal">
                                                    <X class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                            <div v-else class="font-bold text-slate-900">
                                                {{ brand.name }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3.5 text-center">
                                            <span class="px-2.5 py-0.5 bg-slate-100 text-slate-700 rounded-full text-[10px] font-bold">
                                                {{ brand.products_count || 0 }} produk
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3.5 text-right">
                                            <div v-if="editingBrandId !== brand.id" class="flex items-center justify-end gap-1">
                                                <button 
                                                    @click="startEditBrand(brand, $event)"
                                                    type="button"
                                                    class="p-1.5 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition cursor-pointer"
                                                    title="Edit Nama Merk"
                                                >
                                                    <Pencil class="w-3.5 h-3.5" />
                                                </button>
                                                <button 
                                                    @click="deleteBrand(brand, $event)"
                                                    type="button"
                                                    class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                                    title="Hapus Merk"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SUB-TAB 3: SATUAN (UNITS) -->
                    <div v-else class="space-y-4">
                        <!-- Add Form & Search -->
                        <div class="flex flex-col sm:flex-row gap-3">
                            <form @submit.prevent="submitNewUnit" class="flex-1 flex gap-2">
                                <input 
                                    v-model="newUnitNameInput"
                                    type="text"
                                    required
                                    placeholder="Nama satuan baru (misal: Roll / Dus)..."
                                    class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-900 font-semibold focus:outline-none focus:border-amber-500 focus:bg-white"
                                />
                                <button 
                                    type="submit"
                                    :disabled="isCreatingUnit || !newUnitNameInput.trim()"
                                    class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 shrink-0 transition disabled:opacity-50 cursor-pointer shadow-xs"
                                >
                                    <Plus class="w-3.5 h-3.5 text-amber-400" />
                                    <span>{{ isCreatingUnit ? 'Menyimpan...' : 'Tambah' }}</span>
                                </button>
                            </form>

                            <div class="relative sm:w-64">
                                <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input 
                                    v-model="masterUnitSearch"
                                    type="text"
                                    placeholder="Cari satuan..."
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                                />
                            </div>
                        </div>

                        <!-- Table List Satuan -->
                        <div class="border border-slate-200 rounded-2xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                        <th class="py-2.5 px-3.5 w-12 text-center">No</th>
                                        <th class="py-2.5 px-3.5">Nama Satuan</th>
                                        <th class="py-2.5 px-3.5 text-center w-32">Jml Pemakaian</th>
                                        <th class="py-2.5 px-3.5 text-right w-24">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-if="filteredMasterUnits.length === 0">
                                        <td colspan="4" class="py-8 text-center text-slate-400 text-xs">
                                            Tidak ada satuan ditemukan.
                                        </td>
                                    </tr>
                                    <tr 
                                        v-for="(unit, idx) in filteredMasterUnits" 
                                        :key="unit.id"
                                        class="hover:bg-slate-50/80 transition"
                                    >
                                        <td class="py-2.5 px-3.5 text-center text-slate-400 font-mono text-[11px]">
                                            {{ idx + 1 }}
                                        </td>
                                        <td class="py-2.5 px-3.5">
                                            <div v-if="editingUnitId === unit.id" class="flex items-center gap-1.5" @click.stop>
                                                <input 
                                                    v-model="editUnitNameInput"
                                                    type="text"
                                                    class="flex-1 bg-white border border-amber-400 rounded-lg px-2.5 py-1 text-xs text-slate-900 font-bold focus:outline-none ring-2 ring-amber-200"
                                                    @keyup.enter="saveEditUnit(unit)"
                                                    @keyup.esc="cancelEditUnit"
                                                    autofocus
                                                />
                                                <button @click="saveEditUnit(unit)" type="button" class="p-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg cursor-pointer" title="Simpan">
                                                    <Check class="w-3.5 h-3.5" />
                                                </button>
                                                <button @click="cancelEditUnit" type="button" class="p-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg cursor-pointer" title="Batal">
                                                    <X class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                            <div v-else class="font-bold text-slate-900">
                                                {{ unit.name }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3.5 text-center">
                                            <span class="px-2.5 py-0.5 bg-slate-100 text-slate-700 rounded-full text-[10px] font-bold">
                                                {{ unit.product_units_count || 0 }}x
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3.5 text-right">
                                            <div v-if="editingUnitId !== unit.id" class="flex items-center justify-end gap-1">
                                                <button 
                                                    @click="startEditUnit(unit, $event)"
                                                    type="button"
                                                    class="p-1.5 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition cursor-pointer"
                                                    title="Edit Nama Satuan"
                                                >
                                                    <Pencil class="w-3.5 h-3.5" />
                                                </button>
                                                <button 
                                                    @click="deleteUnit(unit, $event)"
                                                    type="button"
                                                    class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                                    title="Hapus Satuan"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                    <button 
                        @click="isMasterCategoryBrandModalOpen = false"
                        class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs cursor-pointer shadow-xs"
                    >
                        Selesai
                    </button>
                </div>
            </div>
        </div>

        <!-- Global Master Units Datalist for Autocomplete -->
        <datalist id="master-units-datalist">
            <option v-for="u in (units || [])" :key="u.id" :value="u.name">{{ u.name }}</option>
        </datalist>
    </MainLayout>
</template>
