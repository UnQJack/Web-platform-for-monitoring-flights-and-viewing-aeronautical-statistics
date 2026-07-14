from flask import Flask, request, jsonify
import requests
import pymysql

app = Flask(__name__)

DB_CONFIG = {
    "host": "localhost",
    "port": 8889,
    "user": "root",
    "password": "root",
    "database": "licenta",
    "cursorclass": pymysql.cursors.DictCursor
}


def fetch_rows(sql):
    connection = pymysql.connect(**DB_CONFIG)

    try:
        with connection.cursor() as cursor:
            cursor.execute(sql)
            return cursor.fetchall()
    finally:
        connection.close()


def get_context(message):
    msg = message.lower()

    if "rezerv" in msg:
        return {
            "direct_answer": "Pentru a adauga o rezervare, mergi in pagina <strong>Rezervari</strong>, apasa butonul <strong>Adauga</strong>, selecteaza zborul, utilizatorul si statusul rezervarii, apoi salveaza."
        }

    if "companii" in msg or "companie" in msg:
        rows = fetch_rows("""
            SELECT DISTINCT a.name AS airline_name
            FROM flights f
            JOIN airlines a ON f.airline_id = a.id
            WHERE f.status IN ('Active', 'Scheduled')
            ORDER BY a.name ASC
            LIMIT 10
        """)

        companies = [r["airline_name"] for r in rows]

        return {
            "direct_answer": "Companiile cu oferte disponibile sunt: <strong>" + ", ".join(companies) + "</strong>."
        }

    if "radar" in msg:
        return {
            "radar": fetch_rows("""
                SELECT
                    r.radar_name,
                    r.distance_km,
                    r.azimuth_deg,
                    r.detection_status,
                    r.detected_at,
                    f.flight_number
                FROM radar r
                JOIN flights f ON r.flight_id = f.id
                ORDER BY r.detected_at DESC
                LIMIT 10
            """)
        }

    if "telecom" in msg or "semnal" in msg or "laten" in msg:
        return {
            "telecom": fetch_rows("""
                SELECT
                    t.signal_strength,
                    t.latency_ms,
                    t.packet_loss,
                    t.frequency_band,
                    t.connection_status,
                    t.recorded_at,
                    f.flight_number
                FROM telemetry t
                JOIN flights f ON t.flight_id = f.id
                ORDER BY t.recorded_at DESC
                LIMIT 10
            """)
        }
    
    if "zboruri active" in msg or ("zboruri" in msg and "activ" in msg):
        rows = fetch_rows("""
            SELECT
                f.flight_number,
                f.base_price,
                a.name AS airline_name,
                ao.city AS origin_city,
                ad.city AS destination_city
            FROM flights f
            JOIN airlines a ON f.airline_id = a.id
            JOIN airports ao ON f.origin_airport_id = ao.id
            JOIN airports ad ON f.destination_airport_id = ad.id
            WHERE f.status = 'Active'
            ORDER BY f.base_price ASC
            LIMIT 10
        """)

        if not rows:
            return {
                "direct_answer": "Nu exista zboruri active momentan."
            }

        answer = "Zborurile active disponibile sunt:<br>"

        for r in rows:
            answer += (
                f"• <strong>{r['flight_number']}</strong> - "
                f"{r['airline_name']}, "
                f"{r['origin_city']} → {r['destination_city']}, "
                f"{float(r['base_price']):.2f} RON<br>"
            )

        return {
            "direct_answer": answer
        }

    return {
        "offers": fetch_rows("""
            SELECT
                f.flight_number,
                f.status,
                f.base_price,
                a.name AS airline_name,
                ao.iata_code AS origin_code,
                ao.city AS origin_city,
                ad.iata_code AS destination_code,
                ad.city AS destination_city
            FROM flights f
            JOIN airlines a ON f.airline_id = a.id
            JOIN airports ao ON f.origin_airport_id = ao.id
            JOIN airports ad ON f.destination_airport_id = ad.id
            WHERE f.status IN ('Active', 'Scheduled')
            ORDER BY f.base_price ASC
            LIMIT 10
        """)
    }

def ask_ollama(message, context):
    prompt = f"""
Esti chatbotul SkyTix.

Raspunzi doar in romana.
Raspunsurile trebuie sa fie scurte si clare.
Nu folosi euro. Toate preturile sunt in RON.
Nu spune „pret de baza”, spune „pret”.
Nu mentiona zboruri care nu au legatura directa cu intrebarea.
Daca utilizatorul scrie doar un oras, afiseaza doar zborurile catre acel oras.
Formatul raspunsului:
Am gasit zboruri catre [oras]:
• [cod zbor] - [companie], [origine] → [destinatie], [pret] RON
Daca utilizatorul intreaba „cea mai ieftina oferta”, „cea mai ieftina”, „ieftin” sau „oferta”, nu cauta destinatie.
Alege primul zbor din lista json, deoarece lista este deja ordonata crescator dupa pret.
Format:
Cea mai ieftina oferta este:
• [cod zbor] - [companie], [origine] → [destinatie], [pret] RON
Daca nu exista zboruri, spune:
Nu am gasit zboruri catre aceasta destinatie.


Date din baza de date:
{context}

Intrebarea utilizatorului:
{message}
"""

    response = requests.post(
        "http://127.0.0.1:11434/api/generate",
        json={
            "model": "llama3.1",
            "prompt": prompt,
            "stream": False,
            "options": {
                "temperature": 0.2,
                "num_predict": 250,
                "num_ctx": 4096
            }
        },
        timeout=90
    )

    response.raise_for_status()
    return response.json().get("response", "").strip()


@app.route("/ask", methods=["POST"])
def ask():
    data = request.get_json(silent=True) or {}
    message = data.get("message", "").strip()

    if not message:
        return jsonify({
            "status": "error",
            "answer": "Te rog scrie o intrebare"
        })

    try:
        context = get_context(message)
        if "direct_answer" in context:
            return jsonify({
                "status": "ok",
                "answer": context["direct_answer"]
            })
        answer = ask_ollama(message, context)

        return jsonify({
            "status": "ok",
            "answer": answer
        })

    except Exception as e:
        return jsonify({
            "status": "error",
            "answer": "Eroare in serviciul AI local: " + str(e)
        }), 500


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5055, debug=True)