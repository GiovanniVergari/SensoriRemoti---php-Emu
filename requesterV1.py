import requests

def set_led_color(r, g, b, ip='vergarigiovanni.altervista.org'):
    url = f'http://{ip}/setLed?r={r}&g={g}&b={b}'
    
    try:
        response = requests.get(url)
        
        if response.status_code == 200:
            print(f"Colore del LED impostato a R:{r} G:{g} B:{b}")
        else:
            print("Impossibile impostare il colore del LED")
            
    except requests.exceptions.RequestException as e:
        print(f"Errore nella richiesta: {e}")

# Esempio di utilizzo
set_led_color(0, 255, 0)