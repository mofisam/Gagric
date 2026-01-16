// Product related JavaScript
class ProductManager {
    constructor() {
        this.setupProductFilters();
        this.setupImageUpload();
        this.setupQuantitySelectors();
    }

    setupProductFilters() {
        const filterForm = document.querySelector('#product-filters');
        if (filterForm) {
            filterForm.addEventListener('change', () => {
                this.applyFilters();
            });
        }

        const searchInput = document.querySelector('#product-search');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.applyFilters();
                }, 500);
            });
        }
    }

    applyFilters() {
        const form = document.querySelector('#product-filters');
        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (let [key, value] of formData) {
            if (value) params.append(key, value);
        }

        // Update URL without page reload
        const newUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.replaceState({}, '', newUrl);

        // Show loading state
        this.showLoading();

        // Simulate API call - replace with actual fetch
        setTimeout(() => {
            this.loadProducts(params.toString());
        }, 300);
    }

    async loadProducts(queryString) {
        try {
            const response = await fetch(`/api/products/list?${queryString}`);
            const data = await response.json();
            this.updateProductGrid(data.products);
        } catch (error) {
            console.error('Error loading products:', error);
            agriApp.showToast('Error loading products', 'error');
        } finally {
            this.hideLoading();
        }
    }

    updateProductGrid(products) {
        const grid = document.querySelector('.products-grid');
        if (!grid) return;

        if (products.length === 0) {
            grid.innerHTML = '<div class="no-products">No products found matching your criteria.</div>';
            return;
        }

        grid.innerHTML = products.map(product => `
            <div class="product-card card" onclick="window.location.href='/products/details.php?id=${product.id}'">
                <img src="${product.image_path || '/assets/images/placeholder-product.jpg'}" 
                     alt="${product.name}" class="product-image">
                <div class="product-info">
                    <h3 class="product-title">${product.name}</h3>
                    <p class="product-seller">By ${product.business_name}</p>
                    <div class="product-meta">
                        <span class="product-price">${agriApp.formatCurrency(product.price_per_unit)}</span>
                        <span class="product-unit">per ${product.unit}</span>
                    </div>
                    ${product.grade ? `<span class="badge badge-success">Grade ${product.grade}</span>` : ''}
                    ${product.is_organic ? `<span class="badge badge-success">Organic</span>` : ''}
                </div>
            </div>
        `).join('');
    }

    setupImageUpload() {
        const imageInput = document.querySelector('#product-images');
        const previewContainer = document.querySelector('#image-preview');

        if (imageInput && previewContainer) {
            imageInput.addEventListener('change', (e) => {
                this.handleImageUpload(e.target.files, previewContainer);
            });
        }
    }

    handleImageUpload(files, previewContainer) {
        const existingImages = previewContainer.querySelectorAll('.image-preview-item').length;
        
        if (existingImages + files.length > 5) {
            agriApp.showToast('Maximum 5 images allowed', 'error');
            return;
        }

        Array.from(files).forEach(file => {
            if (!file.type.startsWith('image/')) {
                agriApp.showToast('Please select image files only', 'error');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const previewItem = document.createElement('div');
                previewItem.className = 'image-preview-item';
                previewItem.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-image">&times;</button>
                `;
                
                previewItem.querySelector('.remove-image').addEventListener('click', () => {
                    previewItem.remove();
                });
                
                previewContainer.appendChild(previewItem);
            };
            reader.readAsDataURL(file);
        });
    }

    setupQuantitySelectors() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('quantity-minus')) {
                this.adjustQuantity(e.target, -1);
            } else if (e.target.classList.contains('quantity-plus')) {
                this.adjustQuantity(e.target, 1);
            }
        });
    }

    adjustQuantity(button, change) {
        const input = button.parentNode.querySelector('.quantity-input');
        let value = parseInt(input.value) + change;
        const max = parseInt(input.dataset.max) || 999;
        const min = parseInt(input.dataset.min) || 1;

        value = Math.max(min, Math.min(max, value));
        input.value = value;

        // Update any related calculations
        this.updateProductTotal(input);
    }

    updateProductTotal(input) {
        const container = input.closest('.product-quantity');
        const price = parseFloat(container.dataset.price) || 0;
        const total = price * parseInt(input.value);
        
        const totalElement = container.querySelector('.product-total');
        if (totalElement) {
            totalElement.textContent = agriApp.formatCurrency(total);
        }
    }

    showLoading() {
        const grid = document.querySelector('.products-grid');
        if (grid) {
            grid.style.opacity = '0.5';
            grid.style.pointerEvents = 'none';
        }
    }

    hideLoading() {
        const grid = document.querySelector('.products-grid');
        if (grid) {
            grid.style.opacity = '1';
            grid.style.pointerEvents = 'auto';
        }
    }
}

// Initialize product manager
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.products-grid') || document.querySelector('#product-filters')) {
        new ProductManager();
    }
});