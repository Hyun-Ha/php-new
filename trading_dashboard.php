<?php
// =======================================================
// PHP에서 로컬 JSON 파일 읽어 통계 데이터 로드
// =======================================================

// Python에서 FTP로 업로드한 파일 이름
$stats_file_name = "trading_stats.json";

// 💡 경고 해결: 모든 핵심 변수를 스크립트 초기에 명확히 초기화합니다.
$stats_json = '';
$stats_data = []; 

// 파일 경로: trading_dashboard.php와 같은 디렉터리에 있어야 합니다.
$file_path = __DIR__ . '/' . $stats_file_name;

if (file_exists($file_path)) {
    // 1. 파일 내용 읽기
    $stats_json_content = file_get_contents($file_path);

    // 2. JSON 디코드
    $stats_data_decoded = json_decode($stats_json_content, true);

    if ($stats_data_decoded === null) {
        $stats_data = [
            "totalReturn" => 0.0, 
            "sharpeRatio" => 0.0, 
            "winRate" => 0.0, 
            "profitFactor" => 0.0,
            "maxDrawdown" => 0.0,
            "totalTrades" => 0,
            "avgProfitLoss" => 0.0,
            "avgHoldingTime" => "0h",
            "currentPrice" => 0.0,
            "currentPosition" => "FLAT",
            "message" => "[PHP Error] 통계 파일 손상 또는 JSON 디코드 실패."
        ];
    } else {
        // 성공 시 $stats_data에 디코드된 데이터를 할당하고 메시지 추가
        $stats_data = $stats_data_decoded;
        $stats_data["message"] = "통계 데이터 로드 완료.";
        
        // currentPrice 또는 currentPosition이 누락된 경우를 대비하여 기본값 설정
        if (!isset($stats_data['currentPrice'])) $stats_data['currentPrice'] = 0.0;
        if (!isset($stats_data['currentPosition'])) $stats_data['currentPosition'] = 'FLAT';
    }
} else {
    // 파일이 없을 경우에도 $stats_data가 기본값으로 정의됨
    $stats_data = [
        "totalReturn" => 0.0, 
        "sharpeRatio" => 0.0, 
        "winRate" => 0.0, 
        "profitFactor" => 0.0,
        "maxDrawdown" => 0.0,
        "totalTrades" => 0,
        "avgProfitLoss" => 0.0,
        "avgHoldingTime" => "0h",
        "currentPrice" => 0.0,
        "currentPosition" => "FLAT",
        "message" => "[PHP Error] 통계 파일(" . $stats_file_name . ")을 찾을 수 없습니다. Python에서 업로드되었는지 확인하세요."
    ];
}

// 이 시점에서 $stats_data는 반드시 배열이므로 json_encode 시 경고가 발생하지 않습니다.
$stats_json = json_encode($stats_data); 
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>비트코인 자동거래 통계</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        /* 추가적인 폰트 스타일링 */
        .stat-value {
            font-size: 1.8rem;
            line-height: 1;
        }
        /* 포지션 박스 스타일링 */
        #position-info-box {
            min-height: 60px;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div id="app-container" class="w-full max-w-4xl bg-white p-8 rounded-xl shadow-2xl border border-gray-200">
        <h1 class="text-4xl font-extrabold text-gray-800 mb-8 text-center flex items-center justify-center">
            비트코인 자동거래 통계자료
            <span class="ml-3 text-indigo-500">→</span>
        </h1>
        
        <div id="trading-stats-view" class="mt-4">
        </div>

        <div id="message-box" class="mt-6 p-4 bg-blue-100 border-l-4 border-blue-500 text-blue-800 rounded-lg transition-all duration-300">
            <p id="app-status" class="font-medium">자바스크립트가 로드되기를 기다리는 중입니다...</p>
        </div>

        <button id="action-button" class="mt-8 w-full py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition duration-150 shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50">
            통계 새로고침 (페이지 새로고침)
        </button>
        
        <h2 class="text-2xl font-bold text-gray-800 mt-10 mb-4 border-b pb-2">
            Bitcoin Price Chart (Bitget BTCUSDT Perpetual)
        </h2>
        
        <div class="h-[400px] w-full border border-gray-300 rounded-lg overflow-hidden">
            <div class="tradingview-widget-container" style="height: 100%; width: 100%;">
                <div id="tradingview_chart" style="height: 100%;"></div>
                <script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
                <script type="text/javascript">
                new TradingView.widget(
                    {
                        "autosize": true,
                        "symbol": "BITGET:BTCUSDT.P", 
                        "interval": "15",
                        "timezone": "Asia/Seoul",
                        "theme": "light", 
                        "style": "1",
                        "locale": "kr",
                        "toolbar_bg": "#f1f3f6",
                        "enable_publishing": false,
                        "allow_symbol_change": true,
                        "container_id": "tradingview_chart"
                    }
                );
                </script>
            </div>
        </div>
        <div id="position-info-box" class="mt-4 p-4 rounded-lg shadow-inner bg-gray-100 border-t-4 transition-all duration-300">
            </div>
        </div>
    
   <script>
    // 🚀 PHP에서 읽어온 통계 데이터를 JavaScript 변수로 받습니다.
    const TRADING_STATS_DATA = <?php echo $stats_json; ?>;

    // --- 실시간 가격 업데이트 상수 (함수 외부에 정의) ---
    const BITGET_TICKER_API = "get_realtime_price.php"; 
const PRICE_UPDATE_INTERVAL_MS = 5000; // 5초마다 업데이트 (5000ms)
    let currentStats = TRADING_STATS_DATA; // 실시간 가격 업데이트를 위해 현재 통계 데이터를 저장

    document.addEventListener('DOMContentLoaded', () => {
        console.log('DOM 콘텐츠 로드 완료.');
        
        const statusElement = document.getElementById('app-status');
        const button = document.getElementById('action-button');
        const messageBox = document.getElementById('message-box');
        const statsView = document.getElementById('trading-stats-view');
        const positionInfoBox = document.getElementById('position-info-box');

        // 1. 초기 통계 데이터 렌더링 (가격 표시 요소 생성)
        renderTradingStats(currentStats, statsView);
        
        // 2. 현재 포지션 현황 렌더링 (PNL을 계산하기 위해 현재 가격(trading_stats.json에 있는 15분 주기 가격) 사용)
        renderPositionInfo(currentStats, positionInfoBox);

        // 3. 상태 업데이트 및 버튼 핸들러 설정 (이전과 동일)
        statusElement.textContent = currentStats.message || '통계가 성공적으로 로드되었습니다.';
        // ... (상태 및 버튼 로직 생략) ...

        button.addEventListener('click', () => {
            window.location.reload();
        });
        
        
        // ---------------------------------------------------------------------------
        // 💡💡💡 실시간 가격 업데이트 로직 (렌더링 후 실행) 💡💡💡
        // ---------------------------------------------------------------------------
        const currentPriceElement = document.getElementById('current-price-display'); 

        function fetchRealtimePrice() {
            if (!currentPriceElement) {
                console.warn("가격 표시 요소(ID: current-price-display)를 찾을 수 없습니다. 렌더링 함수를 확인하세요.");
                clearInterval(priceInterval); // 요소가 없으면 갱신 중지
                return;
            }

            fetch(BITGET_TICKER_API)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP Error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.code === "00000" && data.data && data.data.length > 0) {
                        const lastPrice = parseFloat(data.data[0].last);
                        
                        if (!isNaN(lastPrice)) {
                            // 1. "현재 비트코인 가격" 영역 업데이트
                            currentPriceElement.textContent = `$${lastPrice.toFixed(2)}`;
                            currentPriceElement.classList.remove('text-gray-500'); // N/A 색상 제거
                            currentPriceElement.classList.add('text-white'); // 정상 색상 적용
                            
                            // 2. 포지션 정보 PNL 업데이트
                            currentStats.currentPrice = lastPrice; 
                            renderPositionInfo(currentStats, positionInfoBox);
                        } else {
                            // 가격 데이터가 숫자가 아닐 경우
                            currentPriceElement.textContent = "Data Error";
                            console.error("비트겟 API에서 받은 가격 데이터가 유효한 숫자가 아닙니다:", data.data[0].last);
                        }
                    } else {
                        // API 응답 구조 오류
                        currentPriceElement.textContent = "API Fail";
                        currentPriceElement.classList.remove('text-white');
                        currentPriceElement.classList.add('text-gray-500');
                        console.error("비트겟 API 응답 구조 오류:", data);
                    }
                })
                .catch(error => {
                    // 네트워크 또는 HTTP 오류
                    currentPriceElement.textContent = "N/A (Net Err)";
                    currentPriceElement.classList.remove('text-white');
                    currentPriceElement.classList.add('text-gray-500');
                    console.error("실시간 가격 조회 중 오류 발생:", error);
                });
        }

        // 💡 요소가 존재하므로, 실시간 가격 업데이트를 시작합니다.
        if (currentPriceElement) {
            fetchRealtimePrice(); // 즉시 호출
            const priceInterval = setInterval(fetchRealtimePrice, PRICE_UPDATE_INTERVAL_MS); // 5초마다 반복
        }
        // ---------------------------------------------------------------------------
        
        
        // 🎨 통계 데이터를 HTML로 렌더링하는 함수 (메인 UI 부분)
        function renderTradingStats(stats, container) {
            // ... (생략: 이 부분은 변경하지 않았습니다)
            const statItems = [
                { key: 'totalReturn', label: '총 수익', unit: '%', isPercentage: true, color: 'text-green-400' },
                { key: 'sharpeRatio', label: '투자 성과', unit: '', isDecimal: true, color: 'text-yellow-400' },
                { key: 'winRate', label: '수익률', unit: '%', isPercentage: true, color: 'text-blue-400' },
                { key: 'profitFactor', label: '승률 요인', unit: '', isDecimal: true, color: 'text-indigo-400' },
                { key: 'maxDrawdown', label: '최대 손실폭', unit: '%', isPercentage: true, color: 'text-red-400' },
                { key: 'totalTrades', label: '총 거래', unit: '', isInteger: true, color: 'text-gray-400' },
                { key: 'avgProfitLoss', label: '평균 이익/손실(USDT)', unit: '', isCurrency: true, color: 'text-teal-400' },
                { key: 'avgHoldingTime', label: '평균 보유시간', unit: '', isTime: true, color: 'text-purple-400' },
            ];
            
            const formatValue = (key, value) => {
                if (key !== 'totalTrades' && (value === undefined || value === null || (typeof value === 'number' && value === 0))) {
                    if (stats.totalTrades > 0) {
                        if (key === 'winRate') return '0.00%';
                        return '0.00';
                    }
                    return "N/A";
                }

                const item = statItems.find(i => i.key === key);
                if (!item) return value;
                
                if (item.isPercentage) return `${value.toFixed(2)}%`;
                if (item.isCurrency) return `${value.toFixed(2)}${item.unit}`;
                if (item.isDecimal) return `${value.toFixed(2)}`;
                if (item.isTime) return `${value}`;
                if (item.isInteger) return `${value}`;
                return value;
            };

            const statsHtml = `
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    ${statItems.map(item => `
                        <div class="bg-gray-800 p-4 rounded-lg shadow-xl border border-gray-700 h-28 flex flex-col justify-center">
                            <p class="text-sm font-medium text-gray-400 mb-1">${item.label}</p>
                            <p class="stat-value font-extrabold ${item.color}">${formatValue(item.key, stats[item.key])}</p>
                        </div>
                    `).join('')}
                </div>
            `;
            
            const currentPriceValue = (stats.currentPrice === undefined || stats.currentPrice === 0) 
                ? "N/A" 
                : `$${stats.currentPrice.toFixed(2)}`;
                
            const currentPositionValue = (stats.currentPosition === undefined || stats.currentPosition === 'FLAT' || stats.currentPosition === 0 || stats.currentPosition === null) 
                ? "FLAT" 
                : stats.currentPosition.toUpperCase();
                
            const positionColor = currentPositionValue === 'LONG' ? 'text-green-500' : currentPositionValue === 'SHORT' ? 'text-red-500' : 'text-gray-500';
            const priceColor = currentPriceValue === 'N/A' ? 'text-gray-500' : 'text-white';

            const currentInfoHtml = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8">
                    <div class="bg-gray-800 p-4 rounded-lg shadow-xl border border-gray-700 h-32 flex flex-col justify-center">
                        <p class="text-lg font-medium text-gray-400 mb-2">현재 비트코인 가격</p>
                        <h1 id="current-price-display" class="text-4xl font-extrabold ${priceColor}">${currentPriceValue}</h1>
                    </div>
                    <div class="bg-gray-800 p-4 rounded-lg shadow-xl border border-gray-700 h-32 flex flex-col justify-center text-center">
                        <p class="text-lg font-medium text-gray-400 mb-2">현재 포지션</p>
                        <p class="text-4xl font-extrabold ${positionColor}">
                            ${currentPositionValue}
                        </p>
                    </div>
                </div>
            `;
            
            container.innerHTML = statsHtml + currentInfoHtml;
        }
            
        // 💡 포지션 현황 정보를 차트 아래에 렌더링하는 함수
        function renderPositionInfo(stats, container) {
            // ... (생략: 이 부분은 변경하지 않았습니다)
            const position = stats.currentPosition ? stats.currentPosition.toUpperCase() : 'FLAT';
            const entryPrice = stats.entryPrice; 
            const currentPrice = stats.currentPrice; 
            const unrealizedPnlUsd = stats.unrealizedPnlUsd; 

            const entryPriceText = (entryPrice !== undefined && entryPrice !== null)
                ? `$${entryPrice.toFixed(2)}`
                : 'N/A';
                
            const entryTime = stats.entryTime || 'N/A';

            let pnlValue = null;
            let pnlDisplayText = '';
            let pnlDollarText = '';
            let pnlColorClass = 'text-gray-700';
            
            if (entryPrice && currentPrice) {
                let priceDiff = 0;
                const LEVERAGE = 20; 
                
                if (position === 'LONG') {
                    priceDiff = currentPrice - entryPrice;
                } else if (position === 'SHORT') {
                    priceDiff = entryPrice - currentPrice;
                }

                pnlValue = (priceDiff / entryPrice) * LEVERAGE * 100;
                
                if (!isNaN(pnlValue)) {
                    pnlDisplayText = ` (${pnlValue.toFixed(2)}%)`;
                    
                    if (unrealizedPnlUsd !== undefined && unrealizedPnlUsd !== null && unrealizedPnlUsd !== 0) {
                        pnlDollarText = ` **$${unrealizedPnlUsd.toFixed(2)}**`;
                    } else if (unrealizedPnlUsd === 0) {
                        pnlDollarText = ' **$0.00**';
                    }
                    
                    if (pnlValue > 0) {
                        pnlColorClass = 'text-blue-600 font-bold'; 
                    } else if (pnlValue < 0) {
                        pnlColorClass = 'text-red-600 font-bold';
                    }
                }
            }
            
            const pnlHtml = (pnlValue !== null && position !== 'FLAT') 
                ? `<span class="${pnlColorClass}">${pnlDollarText} ${pnlDisplayText}</span>` 
                : '';

            let htmlContent = '';
            let borderColorClass = 'border-gray-300';
            let backgroundColorClass = 'bg-gray-100';

            if (position === 'LONG') {
                borderColorClass = 'border-green-500';
                backgroundColorClass = 'bg-green-50';
                htmlContent = `
                    <p class="text-lg font-semibold text-green-700">🟢 현재 LONG 포지션을 보유 중입니다.</p>
                    <p class="text-sm text-green-600 mt-1">
                        **평균 진입가:** **${entryPriceText}** | 
                        **진입 시각:** **${entryTime}** ${pnlHtml}
                    </p>
                `;
            } else if (position === 'SHORT') {
                borderColorClass = 'border-red-500';
                backgroundColorClass = 'bg-red-50';
                htmlContent = `
                    <p class="text-lg font-semibold text-red-700">🔴 현재 SHORT 포지션을 보유 중입니다.</p>
                    <p class="text-sm text-red-600 mt-1">
                        **평균 진입가:** **${entryPriceText}** | 
                        **진입 시각:** **${entryTime}** ${pnlHtml}
                    </p>
                `;
            } else {
                htmlContent = `
                    <p class="text-lg font-semibold text-gray-700">⚪ 현재 포지션 없음</p>
                    <p class="text-sm text-gray-500 mt-1">새로운 진입 기회를 기다리는 중입니다.</p>
                `;
            }

            container.className = `mt-4 p-4 rounded-lg shadow-inner ${backgroundColorClass} border-t-4 ${borderColorClass} transition-all duration-300`;
            container.innerHTML = htmlContent;
        }
    });
</script>
</body>
</html>