<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이메일 인증</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .header h1 {
            color: #495057;
            margin: 0;
        }
        .content {
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 14px;
            color: #6c757d;
            text-align: center;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 이메일 인증</h1>
        </div>
        
        <div class="content">
            <p>안녕하세요!</p>
            
            @if($type === 'post')
                <p><strong>게시글 작성</strong>을 위한 이메일 인증을 요청하셨습니다.</p>
            @else
                <p><strong>댓글 작성</strong>을 위한 이메일 인증을 요청하셨습니다.</p>
            @endif
            
            <p>아래 버튼을 클릭하여 이메일 인증을 완료해주세요:</p>
            
            <div style="text-align: center;">
                <a href="{{ $verificationUrl }}" class="btn">이메일 인증 완료하기</a>
            </div>
            
            <div class="warning">
                <strong>⚠️ 주의사항:</strong>
                <ul>
                    <li>이 링크는 24시간 동안만 유효합니다</li>
                    <li>인증을 완료하지 않으면 작성하신 내용이 공개되지 않습니다</li>
                    <li>본인이 요청하지 않았다면 이 이메일을 무시해주세요</li>
                </ul>
            </div>
            
            <p>만약 버튼이 작동하지 않는다면, 아래 링크를 복사하여 브라우저 주소창에 붙여넣어 주세요:</p>
            <p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace;">
                {{ $verificationUrl }}
            </p>
        </div>
        
        <div class="footer">
            <p>이 이메일은 {{ config('app.name') }}에서 자동으로 발송되었습니다.</p>
            <p>문의사항이 있으시면 관리자에게 연락해주세요.</p>
        </div>
    </div>
</body>
</html>
