/**
 * Analytics Charts
 * samfedbiz.com - Federal BD Platform
 * 
 * Initializes Chart.js charts for analytics dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Check if analytics data is available
    if (typeof window.analyticsData === 'undefined') {
        console.warn('Analytics data not available');
        return;
    }

    const data = window.analyticsData;

    // Chart.js default configuration
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#64748b';
    
    // Common chart options
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    padding: 16
                }
            }
        },
        scales: {
            x: {
                grid: {
                    color: 'rgba(148, 163, 184, 0.1)'
                },
                ticks: {
                    color: '#64748b'
                }
            },
            y: {
                grid: {
                    color: 'rgba(148, 163, 184, 0.1)'
                },
                ticks: {
                    color: '#64748b'
                },
                beginAtZero: true
            }
        }
    };

    // Initialize Engagement Chart
    const engagementCtx = document.getElementById('engagement-chart');
    if (engagementCtx && data.engagement) {
        const dates = data.engagement.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const totalInteractions = data.engagement.map(item => parseInt(item.total_interactions) || 0);
        const uniqueUsers = data.engagement.map(item => parseInt(item.unique_users) || 0);

        new Chart(engagementCtx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Total Interactions',
                        data: totalInteractions,
                        borderColor: '#14b8a6',
                        backgroundColor: 'rgba(20, 184, 166, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Unique Users',
                        data: uniqueUsers,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#f8fafc',
                        bodyColor: '#f8fafc',
                        borderColor: 'rgba(148, 163, 184, 0.2)',
                        borderWidth: 1
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }

    // Initialize Conversion Chart
    const conversionCtx = document.getElementById('conversion-chart');
    if (conversionCtx && data.conversion) {
        const dates = data.conversion.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const totalOutreach = data.conversion.map(item => parseInt(item.total_outreach) || 0);
        const successfulSends = data.conversion.map(item => parseInt(item.successful_sends) || 0);
        const responsesReceived = data.conversion.map(item => parseInt(item.responses_received) || 0);

        new Chart(conversionCtx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Outreach Sent',
                        data: totalOutreach,
                        backgroundColor: 'rgba(99, 102, 241, 0.6)',
                        borderColor: '#6366f1',
                        borderWidth: 1
                    },
                    {
                        label: 'Successfully Sent',
                        data: successfulSends,
                        backgroundColor: 'rgba(16, 185, 129, 0.6)',
                        borderColor: '#10b981',
                        borderWidth: 1
                    },
                    {
                        label: 'Responses Received',
                        data: responsesReceived,
                        backgroundColor: 'rgba(245, 158, 11, 0.6)',
                        borderColor: '#f59e0b',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#f8fafc',
                        bodyColor: '#f8fafc',
                        borderColor: 'rgba(148, 163, 184, 0.2)',
                        borderWidth: 1
                    }
                }
            }
        });
    }

    // Initialize Content Chart
    const contentCtx = document.getElementById('content-chart');
    if (contentCtx && data.content) {
        const dates = data.content.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const briefsSent = data.content.map(item => parseInt(item.briefs_sent) || 0);
        const openedBriefs = data.content.map(item => parseInt(item.opened_briefs) || 0);

        new Chart(contentCtx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Briefs Sent',
                        data: briefsSent,
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Briefs Opened',
                        data: openedBriefs,
                        borderColor: '#06b6d4',
                        backgroundColor: 'rgba(6, 182, 212, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#f8fafc',
                        bodyColor: '#f8fafc',
                        borderColor: 'rgba(148, 163, 184, 0.2)',
                        borderWidth: 1
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }

    // Initialize Reliability Chart
    const reliabilityCtx = document.getElementById('reliability-chart');
    if (reliabilityCtx && data.reliability) {
        const dates = data.reliability.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const confirmedItems = data.reliability.map(item => parseInt(item.confirmed_items) || 0);
        const developingItems = data.reliability.map(item => parseInt(item.developing_items) || 0);
        const signalItems = data.reliability.map(item => parseInt(item.signal_items) || 0);

        new Chart(reliabilityCtx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Confirmed (≥80%)',
                        data: confirmedItems,
                        backgroundColor: 'rgba(34, 197, 94, 0.7)',
                        borderColor: '#22c55e',
                        borderWidth: 1
                    },
                    {
                        label: 'Developing (50-79%)',
                        data: developingItems,
                        backgroundColor: 'rgba(245, 158, 11, 0.7)',
                        borderColor: '#f59e0b',
                        borderWidth: 1
                    },
                    {
                        label: 'Signals (<50%)',
                        data: signalItems,
                        backgroundColor: 'rgba(239, 68, 68, 0.7)',
                        borderColor: '#ef4444',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                ...commonOptions,
                scales: {
                    ...commonOptions.scales,
                    x: {
                        ...commonOptions.scales.x,
                        stacked: true
                    },
                    y: {
                        ...commonOptions.scales.y,
                        stacked: true
                    }
                },
                plugins: {
                    ...commonOptions.plugins,
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#f8fafc',
                        bodyColor: '#f8fafc',
                        borderColor: 'rgba(148, 163, 184, 0.2)',
                        borderWidth: 1,
                        callbacks: {
                            afterBody: function(context) {
                                if (context[0] && data.reliability[context[0].dataIndex]) {
                                    const avgReliability = parseFloat(data.reliability[context[0].dataIndex].avg_reliability) || 0;
                                    return `Average Reliability: ${avgReliability.toFixed(2)}`;
                                }
                                return '';
                            }
                        }
                    }
                }
            }
        });
    }

    // Add chart animations with GSAP
    if (typeof gsap !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);
        
        // Animate chart cards
        gsap.from('.chart-card', {
            y: 30,
            opacity: 0,
            duration: 0.6,
            stagger: 0.1,
            scrollTrigger: {
                trigger: '.analytics-charts',
                start: 'top 85%',
                toggleActions: 'play none none reverse'
            }
        });
    }

    // Accessibility improvements
    const charts = document.querySelectorAll('canvas');
    charts.forEach(chart => {
        chart.setAttribute('role', 'img');
        chart.setAttribute('tabindex', '0');
        
        // Add keyboard navigation
        chart.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const description = this.getAttribute('aria-label') || 'Chart';
                // Could implement spoken description or modal with chart data
                console.log(`Focused on: ${description}`);
            }
        });
    });

    // Handle chart resize on window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            Chart.instances.forEach(function(chart) {
                chart.resize();
            });
        }, 100);
    });
});

// Export functionality for development/debugging
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        // Chart initialization functions could be exported here
    };
}