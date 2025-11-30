<?php
// PHP 프록시 (cURL 최종 버전 - 헤더 완벽 재구성)
$api_url = "https://https://api.bitget.com/api/v2/mix/market/ticker?symbol=BTCUSDT_UMCBL";

// 응답 헤더 설정 (브라우저에 JSON임을 알림)
header('Content-Type: application/json');

// cURL 초기화
$ch = curl_init();

// ?????? API가 기대하는 모든 필수 HTTP 헤더를 명시적으로 설정 ??????
$headers = array(
    // 1. Bitget API 도메인 지정 (호스팅 환경의 프록시 간섭 방지)
    'Host: api.bitget.com', 
    // 2. 서버가 요청을 일반적인 웹 브라우저 요청으로 인식하게 함 (필수)
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36',
    // 3. 응답 형식이 JSON이어야 함을 명시
    'Accept: application/json',
    // 4. 요청의 콘텐츠 형식이 JSON임을 명시 (GET 요청이지만 대부분의 API는 이 헤더를 기대함)
    'Content-Type: application/json', 
    // 5. 웹 브라우저에서 요청하는 것처럼 보이게 Referer 헤더 추가 (필수 항목은 아니지만 보안 정책 우회에 도움)
    'Referer: ' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/'
);
// ----------------------------------------------------------------------

// cURL 옵션 설정
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 결과를 문자열로 반환
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 연결 타임아웃 5초
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 총 타임아웃 10초
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // 설정한 헤더 적용

// SSL 인증서 검증 무시 옵션 (공유 호스팅 환경에서 필수)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 

// 실행 및 응답 저장
$response = curl_exec($ch);

// 에러 확인
if (curl_errno($ch)) {
    // cURL 자체 에러 (네트워크 실패 등)
    http_response_code(503);
    error_log("cURL Error: " . curl_error($ch)); 
    echo json_encode(["code" => "503", "msg" => "Service Unavailable: PHP cURL Error on host side."]);
} else {
    // 성공적으로 데이터를 받았을 경우
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Bitget API에서 받은 응답을 JSON으로 디코드 시도
    $data = json_decode($response, true);
    
    if ($http_code == 200 && $data && $data['code'] === "00000") {
        // Bitget API 응답이 성공(200)이고 내부 코드도 성공("00000")일 경우
        echo $response;
    } else {
        // Bitget API 응답은 받았지만 실패했을 경우 (400, 404 등)
        http_response_code($http_code);
        
        if ($data) {
             // Bitget에서 JSON 오류 응답을 보냈다면 그대로 전달
             echo $response;
        } else {
            // Bitget에서 JSON이 아닌 다른 응답(예: HTML)을 보냈다면 일반 오류 메시지 출력
            echo json_encode(["code" => (string)$http_code, "msg" => "Bitget API returned non-200 status or unparsable response."]);
        }
    }
}

// cURL 닫기
curl_close($ch);
?>