<?php namespace App\Controllers;

class Competition extends BaseController {
	public function listNational() {
		helper('link');

		$competitions = $this->db->query(<<<QUERY
			select c.ID as ID, c.Name as Name, p.ID as ProvinceID, p.Name as HostName, City, DateBegin, DateEnd, Contestants, Provinces from Competition c
			left join Province p on p.ID = c.Host
			left join (
				select Competition, count(Person) as Contestants, count(distinct(Province)) as Provinces from Contestant
				group by Competition
			) as contestants on c.ID = contestants.Competition
			where c.Level = 'National'
			order by Year desc
		QUERY)->getResultArray();

		$table = new \CodeIgniter\View\Table();
		$table->setTemplate([
			'table_open' => '<table class="table table-striped table-bordered">'
		]);

		$table->setHeading(['data' => '#', 'class' => 'col-order'], 'Nama', 'Tuan Rumah', 'Kota', 'Waktu', 'Peserta', 'Provinsi');

		$competitionsCount = count($competitions);
		for ($i = 0; $i < $competitionsCount; $i++) {
			$c = $competitions[$i];
			$table->addRow(
				$competitionsCount-$i,
				linkCompetitionInfo($c['ID'], $c['Name']),
				linkProvince($c['ProvinceID'], $c['HostName']),
				$c['City'],
				['data' => date_format(date_create($c['DateBegin']), 'd M Y') . ' &ndash; ' . date_format(date_create($c['DateEnd']), 'd M Y'), 'class' => 'col-centered'],
				['data' => $c['Contestants'], 'class' => 'col-centered'],
				['data' => $c['Provinces'], 'class' => 'col-centered']
			);
		}

		return view('competitions', [
			'menu' =>'competition',
			'submenu' => '/',
			'table' => $table->generate()
		]);
	}

	public function listInternational() {
		return $this->listExternal('International', '/internasional');
	}

	public function listRegional() {
		return $this->listExternal('Regional', '/regional');
	}

	public function info($id) {
		$data = $this->getCompetition($id);

		return view('competition_info', array_merge($data, [
			'submenu' => '',
		]));
	}

	public function results($id) {
		helper('score');
		helper('medal');
		helper('link');

		$data = $this->getCompetition($id);
		$competition = $data['competition'];
		$isNational = $data['isNational'];

		$contestants = $this->db->query(<<<QUERY
			select c.ID as ID, c.Rank as 'Rank', p.ID as PersonID, c.Province as ProvinceID, p.Name as Name, pr.Name as ProvinceName, Score, Medal
			from Contestant c
			join Person p on p.ID = c.Person
			left join Province pr on pr.ID = c.Province
			where Competition = ?
			order by c.Rank asc
		QUERY, [$id])->getResultArray();

		$tasks = $this->db->query(<<<QUERY
			select Alias, ScorePr from Task
			where Competition = ?
			order by Alias asc
		QUERY, [$id])->getResultArray();

		$submissions = $this->db->query(<<<QUERY
			select c.ID as ContestantID, t.Alias as TaskAlias, s.Score as TaskScore, t.ScorePr as TaskScorePr
			from Submission s
			join Contestant c on c.ID = s.Contestant
			join Task t on t.ID = s.Task
			where c.Competition = ?
		QUERY, [$id])->getResultArray();

		$taskScores = array();
		foreach ($submissions as $s) {
			if (empty($taskScores[$s['ContestantID']])) {
				$taskScores[$s['ContestantID']] = array();
			}
			$taskScores[$s['ContestantID']][$s['TaskAlias']] = formatScore($s['TaskScore'], $s['TaskScorePr']);
		}

		$table = new \CodeIgniter\View\Table();
		$table->setTemplate([
			'table_open' => '<table class="table table-bordered">'
		]);

		$heading = array(
			['data' => '#', 'class' => 'col-centered'],
			'Nama'
		);
		if ($isNational) {
			$heading[] = 'Provinsi';
			foreach ($tasks as $t) {
				$heading[] = ['data' => $t['Alias'], 'class' => 'col-centered'];
			}
			$heading[] = ['data' => 'Total', 'class' => 'col-centered'];
		}
		$heading[] = 'Medali';
		$table->setHeading($heading);

		foreach ($contestants as $c) {
			$clazz = getMedalClass($c['Medal']);

			$row = array(
				['data' => $c['Rank'], 'class' => 'col-rank ' . $clazz],
				['data' => linkPerson($c['PersonID'], $c['Name']), 'class' => $clazz],
			);
			if ($isNational) {
				$row[] = ['data' => linkProvince($c['ProvinceID'], $c['ProvinceName']), 'class' => 'col-province ' . $clazz];
				foreach ($tasks as $t) {
					$row[] = ['data' => $taskScores[$c['ID']][$t['Alias']], 'class' => 'col-score ' . $clazz];
				}
				$row[] = ['data' => formatScore($c['Score'], $data['competition']['ScorePr']), 'class' => 'col-score ' . $clazz];
			}
			$row[] = ['data' => getMedalName($c['Medal']), 'class' => 'col-medal ' . $clazz];

			$table->addRow($row);
		}

		return view('competition_results', array_merge($data, [
			'submenu' => '/hasil',
			'table' => $table->generate()
		]));
	}

	public function provinces($id) {
		helper('medal');
		helper('link');

		$data = $this->getCompetition($id);

		$medals = $this->getProvinceMedals($id);

		$table = new \CodeIgniter\View\Table();
		$table->setTemplate([
			'table_open' => '<table class="table table-bordered">'
		]);

		$table->setHeading(
			'Provinsi',
			['data' => 'Medali', 'class' => 'col-centered', 'colspan' => 4]
		);

		foreach ($medals as $m) {
			$table->addRow(
				linkProvince($m['ID'], $m['Name']),
				['data' => $m['Golds'] ?? '-', 'class' => 'col-medals ' . getMedalClass('G')],
				['data' => $m['Silvers'] ?? '-', 'class' => 'col-medals ' . getMedalClass('S')],
				['data' => $m['Bronzes'] ?? '-', 'class' => 'col-medals ' . getMedalClass('B')],
				['data' => $m['Participants'] ?? '-', 'class' => 'col-medals'],
			);
		}

		return view('competition_provinces', array_merge($data, [
			'submenu' => '/provinsi',
			'table' => $table->generate()
		]));
	}

	private function listExternal($level, $submenu) {
		helper('link');

		$competitions = $this->db->query(<<<QUERY
			select ID, Name from Competition
			where Level = ?
			order by Year desc
		QUERY, $level)->getResultArray();

		$table = new \CodeIgniter\View\Table();
		$table->setTemplate([
			'table_open' => '<table class="table table-striped table-bordered">'
		]);

		$table->setHeading(['data' => '#', 'class' => 'col-order'], 'Nama');

		$competitionsCount = count($competitions);
		for ($i = 0; $i < $competitionsCount; $i++) {
			$c = $competitions[$i];
			$table->addRow(
				$competitionsCount-$i,
				linkCompetitionInfo($c['ID'], $c['Name'])
			);
		}

		return view('competitions', [
			'menu' =>'competition',
			'submenu' => $submenu,
			'table' => $table->generate()
		]);
	}

	private function getCompetition($id) {
		$competitions = $this->db->query(<<<QUERY
			select c.ID as ID, Level, Year, c.Name as Name, p.Name as HostName, Website, City, DateBegin, DateEnd, Contestants, Provinces, ScorePr from Competition c
			left join Province p on p.ID = c.Host
			left join (
				select Competition, count(Person) as Contestants, count(distinct(Province)) as Provinces from Contestant
				group by Competition
			) as contestants on c.ID = contestants.Competition
			where c.ID = ?
		QUERY, [$id])->getResultArray();

		if (empty($competitions)) {
			throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
		}
		$competition = $competitions[0];

		$data = [
			'menu' => 'competition',
			'competition' => $competition,
			'isNational' => $competition['Level'] == 'National'
		];

		$competitions = $this->db->query(<<<QUERY
			select ID, Year from Competition
			where Level = ?
			and Year in (?, ?)
		QUERY, [$competition['Level'], $competition['Year']-1, $competition['Year']+1])->getResultArray();

		foreach ($competitions as $c) {
			if ($c['Year'] == $competition['Year']-1) {
				$data['prevCompetition'] = $c;
			}
			if ($c['Year'] == $competition['Year']+1) {
				$data['nextCompetition'] = $c;
			}
		}

		return $data;
	}
}
