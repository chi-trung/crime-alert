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
                        backgroundColor: 'rgba(13, 110, 253, 0.85)',
                        borderRadius: 12,
                        barPercentage: 0.5,
                        categoryPercentage: 0.5,
                        maxBarThickness: 36,
                    },
                    {
                        label: 'Cảnh báo đã duyệt',
                        data: window.approvedData,
                        backgroundColor: 'rgba(25, 135, 84, 0.85)',
                        borderRadius: 12,
                        barPercentage: 0.5,
                        categoryPercentage: 0.5,
                        maxBarThickness: 36,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'center',
                        labels: {
                            font: { size: 17, weight: 'bold' },
                            color: '#222',
                            padding: 20
                        }
                    },
                    tooltip: { enabled: true },
                },
                layout: {
                    padding: { top: 16, left: 8, right: 8, bottom: 8 }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 15 }, color: '#444' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#e9ecef' },
                        ticks: {
                            font: { size: 15 },
                            color: '#444',
                            stepSize: 1,
                            callback: function(value) {
                                if (Number.isInteger(value)) {
                                    return value;
                                }
                                return '';
                            }
                        }
                    }
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
        // Chuẩn bị dữ liệu và màu sắc
        let pieLabels = ['Cướp giật', 'Trộm cắp', 'Lừa đảo', 'Bạo lực', 'Khác'];
        let pieColors = ['#e63946', '#0d6efd', '#fd7e14', '#198754', '#6c757d'];
        let pieData = [
            window.typePercentsAdmin['Cướp giật'] ?? 0,
            window.typePercentsAdmin['Trộm cắp'] ?? 0,
            window.typePercentsAdmin['Lừa đảo'] ?? 0,
            window.typePercentsAdmin['Bạo lực'] ?? 0,
            window.typePercentsAdmin['Khác'] ?? 0
        ];
        // Lọc các phần tử > 0
        let filteredLabels = [];
        let filteredColors = [];
        let filteredData = [];
        pieData.forEach((val, idx) => {
            if (val > 0) {
                filteredLabels.push(pieLabels[idx]);
                filteredColors.push(pieColors[idx]);
                filteredData.push(val);
            }
        });
        // Nếu chỉ có 1 loại > 0 thì chỉ truyền 1 phần tử
        if (filteredData.length === 1) {
            pieLabels = filteredLabels;
            pieColors = filteredColors;
            pieData = filteredData;
        }
        alertsPieChartInstance = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieData,
                    backgroundColor: pieColors,
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
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed || 0;
                                return label + ': ' + value + '%';
                            }
                        }
                    }
                }
            }
        });
    }
}); 