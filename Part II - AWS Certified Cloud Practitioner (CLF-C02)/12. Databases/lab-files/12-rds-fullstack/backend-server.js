require('dotenv').config();
const express = require('express');
const cors    = require('cors');
const pool    = require('./db');

const app = express();
app.use(cors());
app.use(express.json());

(async () => {
  try {
    await pool.query(`
      CREATE TABLE IF NOT EXISTS students (
        id           SERIAL PRIMARY KEY,
        first_name   VARCHAR(50)  NOT NULL,
        last_name    VARCHAR(50)  NOT NULL,
        age          INT,
        college_name VARCHAR(100),
        program_name VARCHAR(100),
        year         INT,
        semester     VARCHAR(20)
      )
    `);
    console.log('students table ready');
  } catch (err) {
    console.error('Error creating students table:', err.message);
  }
})();

app.post('/api/students', async (req, res) => {
  try {
    const { first_name, last_name, age, college_name, program_name, year, semester } = req.body;

    if (!first_name || !last_name) {
      return res.status(400).json({ error: 'First name and last name are required.' });
    }

    const result = await pool.query(
      `INSERT INTO students (first_name, last_name, age, college_name, program_name, year, semester)
       VALUES ($1, $2, $3, $4, $5, $6, $7)
       RETURNING id`,
      [first_name, last_name, age, college_name, program_name, year, semester]
    );

    res.json({ id: result.rows[0].id });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get('/api/students', async (req, res) => {
  try {
    const result = await pool.query('SELECT * FROM students ORDER BY id DESC');
    res.json(result.rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get('/api/students/search', async (req, res) => {
  try {
    const { first_name, last_name } = req.query;

    if (!first_name || !last_name) {
      return res.status(400).json({ error: 'first_name and last_name query params are required.' });
    }

    const result = await pool.query(
      `SELECT * FROM students
       WHERE LOWER(first_name) = LOWER($1) AND LOWER(last_name) = LOWER($2)
       LIMIT 1`,
      [first_name, last_name]
    );

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'No student found.' });
    }

    res.json(result.rows[0]);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.put('/api/students/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const { first_name, last_name, age, college_name, program_name, year, semester } = req.body;

    if (!first_name || !last_name) {
      return res.status(400).json({ error: 'First name and last name are required.' });
    }

    const result = await pool.query(
      `UPDATE students
       SET first_name=$1, last_name=$2, age=$3, college_name=$4,
           program_name=$5, year=$6, semester=$7
       WHERE id=$8`,
      [first_name, last_name, age, college_name, program_name, year, semester, id]
    );

    res.json({ success: result.rowCount > 0 });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.delete('/api/students/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const result = await pool.query('DELETE FROM students WHERE id=$1', [id]);
    res.json({ success: result.rowCount > 0 });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

const PORT = process.env.PORT || 4000;
app.listen(PORT, () => console.log(`Server running on port ${PORT}`));
