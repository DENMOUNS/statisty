<div class="statisty-chart">
    <canvas id="{{ $id }}"></canvas>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('{{ $id }}');
            if (!ctx) return;
            const data = @json($data);
            new Chart(ctx, {
                type: data.datasets[0].type ?? 'line',
                data: {
                    labels: data.labels,
                    datasets: data.datasets,
                },
                options: data.options ?? {}
            });
        });
    </script>
</div>
