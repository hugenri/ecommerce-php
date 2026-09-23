(function () {
        const data = window.dashboardChartData;
        const canvas = document.getElementById("salesChart");
        if (!data || !data.labels || !canvas) return;

        new Chart(canvas.getContext("2d"), {
            type: "bar",
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: "Pedidos",
                        data: data.orders,
                        backgroundColor: "rgba(13, 110, 253, 0.7)",
                        yAxisID: "y"
                    },
                    {
                        label: "Total (\$)",
                        data: data.totals,
                        type: "line",
                        borderColor: "#198754",
                        backgroundColor: "rgba(25, 135, 84, 0.1)",
                        pointBackgroundColor: "#198754",
                        yAxisID: "y1",
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, position: "left", grid: { borderWidth: 0 } },
                    y1: { beginAtZero: true, position: "right", grid: { drawOnChartArea: false } }
                }
            }
        });
    })();