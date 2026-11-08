@component('mail::message')
# Confirmação de Pagamento PIX (Usuário)

**Data/Hora:** {{ $payload['datetime'] }}  
**IP:** {{ $payload['ip'] }}  
**User-Agent:** {{ $payload['user_agent'] }}

---

## Dados do Usuário
**ID:** {{ $payload['user']['id'] }}  
**Nome:** {{ $payload['user']['name'] }}  
**E-mail:** {{ $payload['user']['email'] }}  
**Telefone:** {{ $payload['user']['telefone'] ?? '-' }}  
**CPF:** {{ $payload['user']['cpf'] ?? '-' }}

---

**Plano:** R$ {{ number_format($payload['amount'], 2, ',', '.') }} / mês  
**Chave PIX:** {{ $payload['pix_key'] }}

@endcomponent