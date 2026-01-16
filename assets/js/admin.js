// Admin specific JavaScript
class AdminManager {
    constructor() {
        this.setupDataTables();
        this.setupApprovalActions();
        this.setupCharts();
        this.setupBulkActions();
    }

    setupDataTables() {
        // Simple table sorting and filtering
        const tables = document.querySelectorAll('.table');
        tables.forEach(table => {
            const headers = table.querySelectorAll('th[data-sort]');
            headers.forEach(header => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => {
                    this.sortTable(table, header.cellIndex, header.dataset.sort);
                });
            });
        });
    }

    sortTable(table, columnIndex, dataType) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const isAscending = !tbody.dataset.sortedAsc;

        rows.sort((a, b) => {
            let aValue = a.cells[columnIndex].textContent.trim();
            let bValue = b.cells[columnIndex].textContent.trim();

            if (dataType === 'number') {
                aValue = parseFloat(aValue) || 0;
                bValue = parseFloat(bValue) || 0;
            } else if (dataType === 'date') {
                aValue = new Date(aValue);
                bValue = new Date(bValue);
            }

            if (aValue < bValue) return isAscending ? -1 : 1;
            if (aValue > bValue) return isAscending ? 1 : -1;
            return 0;
        });

        // Clear existing rows
        while (tbody.firstChild) {
            tbody.removeChild(tbody.firstChild);
        }

        // Append sorted rows
        rows.forEach(row => tbody.appendChild(row));
        tbody.dataset.sortedAsc = isAscending;
    }

    setupApprovalActions() {
        document.addEventListener('click', async (e) => {
            if (e.target.classList.contains('approve-btn')) {
                await this.handleApproval(e.target, 'approved');
            } else if (e.target.classList.contains('reject-btn')) {
                await this.handleApproval(e.target, 'rejected');
            }
        });
    }

    async handleApproval(button, action) {
        const productId = button.dataset.productId;
        const notes = prompt(action === 'rejected' ? 'Reason for rejection:' : 'Approval notes:');

        if (action === 'rejected' && !notes) {
            agriApp.showToast('Rejection reason is required', 'error');
            return;
        }

        try {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            const response = await fetch('/api/admin/approvals.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    action: action,
                    notes: notes
                })
            });

            const result = await response.json();

            if (result.success) {
                agriApp.showToast(`Product ${action} successfully`, 'success');
                // Remove row from table
                const row = button.closest('tr');
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            } else {
                throw new Error(result.error || 'Action failed');
            }

        } catch (error) {
            console.error('Approval error:', error);
            agriApp.showToast(error.message, 'error');
            button.disabled = false;
            button.innerHTML = action === 'approved' ? 'Approve' : 'Reject';
        }
    }

    setupCharts() {
        // Simple chart implementation - integrate with Chart.js in production
        const chartContainers = document.querySelectorAll('[data-chart]');
        chartContainers.forEach(container => {
            this.renderSimpleChart(container);
        });
    }

    renderSimpleChart(container) {
        const chartType = container.dataset.chart;
        const data = JSON.parse(container.dataset.values || '[]');
        
        // Simple bar chart using CSS
        if (chartType === 'bar') {
            container.innerHTML = data.map(item => `
                <div class="chart-bar" style="height: ${item.value}%">
                    <span class="chart-label">${item.label}</span>
                    <span class="chart-value">${item.value}</span>
                </div>
            `).join('');
        }
    }

    setupBulkActions() {
        const bulkSelect = document.querySelector('#bulk-select');
        const bulkAction = document.querySelector('#bulk-action');
        const bulkExecute = document.querySelector('#bulk-execute');

        if (bulkSelect && bulkAction && bulkExecute) {
            bulkSelect.addEventListener('change', (e) => {
                const checkboxes = document.querySelectorAll('.row-select:checked');
                bulkExecute.disabled = checkboxes.length === 0;
            });

            bulkExecute.addEventListener('click', () => {
                this.executeBulkAction(bulkAction.value);
            });
        }
    }

    async executeBulkAction(action) {
        const selectedIds = Array.from(document.querySelectorAll('.row-select:checked'))
            .map(checkbox => checkbox.value);

        if (selectedIds.length === 0) {
            agriApp.showToast('Please select items to perform bulk action', 'warning');
            return;
        }

        if (!confirm(`Are you sure you want to ${action} ${selectedIds.length} item(s)?`)) {
            return;
        }

        try {
            const response = await fetch('/api/admin/bulk-actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    ids: selectedIds
                })
            });

            const result = await response.json();

            if (result.success) {
                agriApp.showToast(`Bulk ${action} completed successfully`, 'success');
                // Reload page to reflect changes
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error(result.error || 'Bulk action failed');
            }

        } catch (error) {
            console.error('Bulk action error:', error);
            agriApp.showToast(error.message, 'error');
        }
    }

    // Export data functionality
    async exportData(type, format = 'csv') {
        try {
            const response = await fetch(`/api/admin/export.php?type=${type}&format=${format}`);
            const blob = await response.blob();
            
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${type}-export.${format}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            agriApp.showToast('Export completed successfully', 'success');
        } catch (error) {
            console.error('Export error:', error);
            agriApp.showToast('Export failed', 'error');
        }
    }
}

// Initialize admin manager
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.dashboard-main')) {
        new AdminManager();
    }
});