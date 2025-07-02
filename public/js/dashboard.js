// Chart.js Script cho dashboard
// Đảm bảo các biến createdData, approvedData, typePercentsAdmin đã được truyền từ blade sang window object

let alertsChartInstance = null;
let alertsPieChartInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    // Refresh button animation
    const refreshBtn = document.getElementById('refresh-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.classList.add('refreshing');
            setTimeout(() => {
                this.classList.remove('refreshing');
                location.reload();
            }, 1000);
        });
    }

    if (typeof Chart !== 'undefined' && window.createdData && window.approvedData) {
        // Bar Chart (Cảnh báo theo tháng)
        const ctx = document.getElementById('alertsChart').getContext('2d');
        if (alertsChartInstance) {
            alertsChartInstance.destroy();
        }
        alertsChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'],
                datasets: [
                    {
                        label: 'Cảnh báo đã tạo',
                        data: window.createdData,
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderRadius: 10,
                        barPercentage: 0.5,
                        categoryPercentage: 0.5,
                        maxBarThickness: 32,
                    },
                    {
                        label: 'Cảnh báo đã duyệt',
                        data: window.approvedData,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
                        borderRadius: 10,
                        barPercentage: 0.5,
                        categoryPercentage: 0.5,
                        maxBarThickness: 32,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top', labels: { font: { size: 15 } } },
                    tooltip: { enabled: true }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#f0f0f0' } }
                }
            }
        });
    }

    if (typeof Chart !== 'undefined' && window.typePercentsAdmin) {
        // Donut Chart (Phân loại cảnh báo)
        const pieCtx = document.getElementById('alertsPieChart').getContext('2d');
        if (alertsPieChartInstance) {
            alertsPieChartInstance.destroy();
        }
        alertsPieChartInstance = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Cướp giật', 'Trộm cắp', 'Lừa đảo', 'Bạo lực', 'Khác'],
                datasets: [{
                    data: [
                        window.typePercentsAdmin['Cướp giật'] ?? 0,
                        window.typePercentsAdmin['Trộm cắp'] ?? 0,
                        window.typePercentsAdmin['Lừa đảo'] ?? 0,
                        window.typePercentsAdmin['Bạo lực'] ?? 0,
                        window.typePercentsAdmin['Khác'] ?? 0
                    ],
                    backgroundColor: [
                        '#e63946', // Cướp giật
                        '#0d6efd', // Trộm cắp
                        '#fd7e14', // Lừa đảo
                        '#198754', // Bạo lực
                        '#6c757d'  // Khác
                    ],
                    borderWidth: 4,
                    borderColor: '#fff',
                    hoverOffset: 16
                }]
            },
            options: {
                cutout: '70%',
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'right', labels: { font: { size: 16 } } },
                    tooltip: { enabled: true }
                }
            }
        });
    }
}); 